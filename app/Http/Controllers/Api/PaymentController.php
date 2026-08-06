<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\TokenPurchase;
use App\Services\Payment\DokuService;
use App\Services\TokenWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private DokuService $doku, private TokenWalletService $tokenWallet) {}

    /**
     * Mulai pembayaran DOKU untuk satu sample tier comprehensive/master.
     * Hanya pemilik sample (klien) yang boleh membayar - grafolog/pihak lain
     * yang membuatkan sample tidak otomatis boleh bayar atas nama klien.
     * `discount_code` opsional (Commerce Fase D) - divalidasi lewat
     * DiscountCode::isValidFor() yang sama dipakai PricingController::preview,
     * jangan duplikasi cek di sini.
     */
    public function store(CreatePaymentRequest $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($sample->user_id === $user->id, 403, 'Anda bukan pemilik sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Tier rapid gratis, tidak perlu pembayaran.');
        abort_if($sample->payments()->where('status', 'paid')->exists(), 422, 'Sample ini sudah dibayar.');

        $baseAmount = PricingPlan::activePriceFor($sample->tier);
        abort_if($baseAmount === null, 422, "Harga untuk tier {$sample->tier} belum dikonfigurasi.");

        $discountCode = null;
        $amount = $baseAmount;
        if ($codeInput = $request->validated('discount_code')) {
            $discountCode = DiscountCode::where('code', strtoupper($codeInput))->first();
            abort_unless($discountCode?->isValidFor($sample->tier), 422, 'Kode diskon tidak berlaku.');
            $amount = $baseAmount - $discountCode->amountOff($baseAmount);
        }

        $payment = Payment::create([
            'sample_id' => $sample->id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.$sample->id.'-'.Str::upper(Str::random(6)),
            'base_amount' => $baseAmount,
            'amount' => $amount,
            'discount_code_id' => $discountCode?->id,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        // DokuService melempar RuntimeException kalau kredensial belum diisi
        // atau DOKU menolak request - jangan biarkan itu bocor jadi 500 mentah
        // (leak stack trace/nama env var kalau APP_DEBUG kelupaan aktif di
        // production). Detail asli tetap masuk log server untuk developer.
        try {
            $checkout = $this->doku->createCheckout(
                $payment->invoice_number,
                $payment->amount,
                $payment->currency,
                $user->name,
                $user->email,
            );
        } catch (RuntimeException $e) {
            Log::error('DOKU checkout gagal dibuat', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Pembayaran sedang tidak tersedia, coba lagi nanti.',
            ], 503);
        }

        $payment->update([
            'doku_token_id' => $checkout['token_id'],
            'doku_payment_url' => $checkout['url'],
        ]);

        return response()->json([
            'payment_url' => $checkout['url'],
            'invoice_number' => $payment->invoice_number,
        ], 201);
    }

    /**
     * Webhook DOKU (server-to-server, bukan browser) - TIDAK di belakang
     * auth:sanctum karena DOKU tidak punya token kita. Keamanan bergantung
     * penuh pada verifikasi Signature, jangan pernah dilewati.
     *
     * PERHATIAN: struktur body di bawah (`order.invoice_number`,
     * `transaction.status`) disusun dari dokumentasi resmi DOKU yang
     * ditemukan 2026-07-27, TAPI belum pernah dicek melawan notifikasi
     * sungguhan dari DOKU Sandbox. Kirim satu transaksi test dari Sandbox
     * dan bandingkan payload asli (cek `notification_payload` yang disimpan
     * di baris payments) sebelum mengandalkan ini di production.
     *
     * DOKU Back Office hanya punya SATU Notification URL, dipakai untuk
     * semua jenis transaksi - jadi satu route ini menangani baik
     * pembayaran sample klien (Payment) maupun pembelian token grafolog
     * (TokenPurchase). Dibedakan lewat prefix invoice_number ('INV-' vs
     * 'TOKEN-'), dicoba cari di Payment dulu baru TokenPurchase.
     */
    public function notification(Request $request): JsonResponse
    {
        if (! $this->doku->verifyNotificationSignature($request)) {
            Log::warning('DOKU notification: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid Signature'], 400);
        }

        $invoiceNumber = $request->input('order.invoice_number');
        $status = $request->input('transaction.status');

        if ($payment = Payment::where('invoice_number', $invoiceNumber)->first()) {
            $this->handleSamplePaymentNotification($payment, $status, $request);

            return response()->json(['message' => 'OK']);
        }

        if ($purchase = TokenPurchase::where('invoice_number', $invoiceNumber)->first()) {
            $this->handleTokenPurchaseNotification($purchase, $status, $request);

            return response()->json(['message' => 'OK']);
        }

        Log::warning('DOKU notification: unknown invoice_number', ['invoice_number' => $invoiceNumber]);

        return response()->json(['message' => 'Unknown invoice_number'], 404);
    }

    private function handleSamplePaymentNotification(Payment $payment, string $status, Request $request): void
    {
        $wasAlreadyPaid = $payment->isPaid();

        $payment->update([
            'notification_payload' => $request->all(),
            'status' => $status === 'SUCCESS' ? 'paid' : 'failed',
            'paid_at' => $status === 'SUCCESS' ? now() : null,
        ]);

        // Kuota kode diskon baru terpakai saat pembayaran BENAR-BENAR sukses,
        // bukan saat preview atau saat Payment dibuat - dan hanya sekali,
        // jaga-jaga DOKU mengirim notifikasi SUCCESS dobel untuk invoice yang sama.
        if ($status === 'SUCCESS' && ! $wasAlreadyPaid) {
            $payment->discountCode?->incrementUsage();
        }
    }

    private function handleTokenPurchaseNotification(TokenPurchase $purchase, string $status, Request $request): void
    {
        $wasAlreadyPaid = $purchase->isPaid();

        $purchase->update([
            'notification_payload' => $request->all(),
            'status' => $status === 'SUCCESS' ? 'paid' : 'failed',
            'paid_at' => $status === 'SUCCESS' ? now() : null,
        ]);

        // Sama seperti Payment: kuota diskon & saldo token baru bertambah
        // saat SUKSES sungguhan, sekali saja, dijaga dari notifikasi dobel.
        if ($status === 'SUCCESS' && ! $wasAlreadyPaid) {
            $this->tokenWallet->credit($purchase->user, $purchase->quantity, ['token_purchase_id' => $purchase->id]);
            $purchase->discountCode?->incrementUsage();
        }
    }
}
