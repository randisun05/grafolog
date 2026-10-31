<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaymentRequest;
use App\Models\AuditLog;
use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\TokenPurchase;
use App\Services\Commerce\OrderService;
use App\Services\Payment\DokuService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\XenditService;
use App\Services\Payment\PaymentMethods;
use App\Services\Payment\PaymentMode;
use App\Services\TokenWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private DokuService $doku,
        private XenditService $xendit,
        private PaymentGatewayManager $gateways,
        private TokenWalletService $tokenWallet,
    ) {}

    /** Gateway yang bisa dipilih pembeli (kredensial terisi), default di urutan pertama - publik, tanpa rahasia. */
    public function gateways(): JsonResponse
    {
        return response()->json(['default' => $this->gateways->defaultName(), 'available' => $this->gateways->available()]);
    }

    /**
     * Mulai pembayaran (DOKU/Xendit) untuk satu sample tier comprehensive/master.
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
            abort_unless($discountCode?->isValidFor($sample->tier, $request->user()->isHr() ? 'b2b' : 'client'), 422, 'Kode diskon tidak berlaku.');
            $amount = $baseAmount - $discountCode->amountOff($baseAmount);
        }

        $methodTypes = PaymentMethods::resolve($discountCode, $request->validated('payment_method'));

        $payment = Payment::create([
            'sample_id' => $sample->id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.$sample->id.'-'.Str::upper(Str::random(6)),
            'base_amount' => $baseAmount,
            'amount' => $amount,
            'discount_code_id' => $discountCode?->id,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        if (PaymentMode::bypassed()) {
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'notification_payload' => ['bypass' => true]]);
            $payment->discountCode?->incrementUsage();
            AuditLog::record('pembayaran_mode_uji', 'payment', $payment->id, $user->id, $request->ip());

            return response()->json([
                'paid' => true,
                'bypass' => true,
                'invoice_number' => $payment->invoice_number,
            ], 201);
        }

        $gateway = $this->gateways->resolve($request->validated('gateway'));

        // Gateway melempar RuntimeException kalau kredensial belum diisi
        // atau gateway menolak request - jangan biarkan itu bocor jadi 500 mentah
        // (leak stack trace/nama env var kalau APP_DEBUG kelupaan aktif di
        // production). Detail asli tetap masuk log server untuk developer.
        try {
            $checkout = $gateway->createCheckout(
                $payment->invoice_number,
                $payment->amount,
                $payment->currency,
                $user->name,
                $user->email,
                $methodTypes,
            );
        } catch (RuntimeException $e) {
            Log::error('Checkout gagal dibuat', ['gateway' => $gateway->name(), 'payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Pembayaran sedang tidak tersedia, coba lagi nanti.',
            ], 503);
        }

        $payment->update([
            'gateway' => $gateway->name(),
            'gateway_ref' => $checkout['ref'],
            'gateway_url' => $checkout['url'],
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

        return $this->dispatchNotification(
            'DOKU',
            $request->input('order.invoice_number'),
            (string) $request->input('transaction.status'),
            $request->input('transaction.status') === 'SUCCESS',
            $request,
        );
    }

    /**
     * Webhook Xendit (Callback URL invoice) - server-to-server, TIDAK di belakang auth:sanctum;
     * keamanan = header X-Callback-Token. Payload datar: `external_id` (= invoice_number kita),
     * `status` PAID/SETTLED = lunas, EXPIRED/lainnya = gagal. Belum dicek terhadap payload
     * sungguhan Xendit: periksa `notification_payload` yang tersimpan pada transaksi test pertama.
     */
    public function xenditNotification(Request $request): JsonResponse
    {
        if (! $this->xendit->verifyWebhookToken($request)) {
            Log::warning('Xendit notification: invalid callback token', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid Callback Token'], 400);
        }

        $status = (string) $request->input('status');

        return $this->dispatchNotification(
            'Xendit',
            $request->input('external_id'),
            $status,
            in_array($status, ['PAID', 'SETTLED'], true),
            $request,
        );
    }

    /** Cari transaksi (Payment dulu, lalu TokenPurchase) lalu terapkan hasil bayar - dipakai kedua webhook. */
    private function dispatchNotification(string $gateway, ?string $invoiceNumber, string $status, bool $success, Request $request): JsonResponse
    {
        if ($payment = Payment::where('invoice_number', $invoiceNumber)->first()) {
            $this->handleSamplePaymentNotification($payment, $status, $success, $request);

            return response()->json(['message' => 'OK']);
        }

        if ($purchase = TokenPurchase::where('invoice_number', $invoiceNumber)->first()) {
            $this->handleTokenPurchaseNotification($purchase, $status, $success, $request);

            return response()->json(['message' => 'OK']);
        }

        Log::warning($gateway.' notification: unknown invoice_number', ['invoice_number' => $invoiceNumber]);

        return response()->json(['message' => 'Unknown invoice_number'], 404);
    }

    private function handleSamplePaymentNotification(Payment $payment, string $status, bool $success, Request $request): void
    {
        $wasAlreadyPaid = $payment->isPaid();

        // Status lunas bersifat final: notifikasi susulan (FAILED/EXPIRED atau
        // SUCCESS dobel) tidak boleh menurunkan status atau mengkredit ulang.
        if ($wasAlreadyPaid) {
            Log::info('Notification diabaikan, invoice sudah lunas', ['invoice_number' => $payment->invoice_number, 'status' => $status]);

            return;
        }

        $payment->update([
            'notification_payload' => $request->all(),
            'status' => $success ? 'paid' : 'failed',
            'paid_at' => $success ? now() : null,
        ]);

        // Kuota kode diskon baru terpakai saat pembayaran BENAR-BENAR sukses,
        // bukan saat preview atau saat Payment dibuat - dan hanya sekali,
        // jaga-jaga DOKU mengirim notifikasi SUCCESS dobel untuk invoice yang sama.
        if ($success && ! $wasAlreadyPaid) {
            $payment->discountCode?->incrementUsage();
            // Pembayaran pesanan produk (2026-10-17): buat layanan pendukungnya.
            if ($payment->order) {
                app(OrderService::class)->markPaid($payment->order);
            }
            if ($payment->orderBatch) {
                app(OrderService::class)->markBatchPaid($payment->orderBatch);
            }
        }
    }

    private function handleTokenPurchaseNotification(TokenPurchase $purchase, string $status, bool $success, Request $request): void
    {
        $wasAlreadyPaid = $purchase->isPaid();

        // Status lunas bersifat final: notifikasi susulan (FAILED/EXPIRED atau
        // SUCCESS dobel) tidak boleh menurunkan status atau mengkredit ulang.
        if ($wasAlreadyPaid) {
            Log::info('Notification diabaikan, invoice sudah lunas', ['invoice_number' => $purchase->invoice_number, 'status' => $status]);

            return;
        }

        $purchase->update([
            'notification_payload' => $request->all(),
            'status' => $success ? 'paid' : 'failed',
            'paid_at' => $success ? now() : null,
        ]);

        // Sama seperti Payment: kuota diskon & saldo token baru bertambah
        // saat SUKSES sungguhan, sekali saja, dijaga dari notifikasi dobel.
        if ($success && ! $wasAlreadyPaid) {
            $this->tokenWallet->credit($purchase->user, $purchase->quantity, ['token_purchase_id' => $purchase->id]);
            $purchase->discountCode?->incrementUsage();
        }
    }
}
