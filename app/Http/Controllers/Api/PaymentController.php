<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Services\Payment\DokuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(private DokuService $doku) {}

    /**
     * Mulai pembayaran DOKU untuk satu sample tier comprehensive/master.
     * Hanya pemilik sample (klien) yang boleh membayar - grafolog/pihak lain
     * yang membuatkan sample tidak otomatis boleh bayar atas nama klien.
     */
    public function store(Request $request, HandwritingSample $sample): JsonResponse
    {
        $user = $request->user();
        abort_unless($sample->user_id === $user->id, 403, 'Anda bukan pemilik sample ini.');
        abort_if($sample->tier === 'rapid', 422, 'Tier rapid gratis, tidak perlu pembayaran.');
        abort_if($sample->payments()->where('status', 'paid')->exists(), 422, 'Sample ini sudah dibayar.');

        $amount = config("pricing.tiers.{$sample->tier}");
        abort_if($amount === null, 422, "Harga untuk tier {$sample->tier} belum dikonfigurasi.");

        $payment = Payment::create([
            'sample_id' => $sample->id,
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.$sample->id.'-'.Str::upper(Str::random(6)),
            'amount' => $amount,
            'currency' => 'IDR',
            'status' => 'pending',
        ]);

        $checkout = $this->doku->createCheckout($payment, $user->name, $user->email);

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
     */
    public function notification(Request $request): JsonResponse
    {
        if (! $this->doku->verifyNotificationSignature($request)) {
            Log::warning('DOKU notification: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid Signature'], 400);
        }

        $invoiceNumber = $request->input('order.invoice_number');
        $status = $request->input('transaction.status');

        $payment = Payment::where('invoice_number', $invoiceNumber)->first();
        if (! $payment) {
            Log::warning('DOKU notification: unknown invoice_number', ['invoice_number' => $invoiceNumber]);

            return response()->json(['message' => 'Unknown invoice_number'], 404);
        }

        $payment->update([
            'notification_payload' => $request->all(),
            'status' => $status === 'SUCCESS' ? 'paid' : 'failed',
            'paid_at' => $status === 'SUCCESS' ? now() : null,
        ]);

        return response()->json(['message' => 'OK']);
    }
}
