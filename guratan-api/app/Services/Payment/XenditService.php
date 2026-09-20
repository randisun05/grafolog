<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrasi Xendit Invoices (https://docs.xendit.co/docs/invoice-overview).
 * Kontrak API di bawah (endpoint, field request/response, skema verifikasi
 * webhook) dicek langsung ke dokumentasi resmi Xendit 2026-09-20 - JANGAN
 * diubah tanpa verifikasi ulang, signature/token yang salah gagal di sisi
 * Xendit tanpa pesan yang jelas.
 *
 * Beda mendasar dari DokuService (provider lama, digantikan 2026-09-20
 * atas permintaan langsung user): SATU base URL untuk sandbox maupun
 * production (`https://api.xendit.co`) - mode ditentukan dari PREFIX
 * secret key itu sendiri (`xnd_development_...` vs `xnd_production_...`),
 * bukan dari host/flag terpisah seperti DOKU_IS_PRODUCTION. Autentikasi
 * pakai HTTP Basic Auth (secret key sebagai username, password kosong),
 * bukan HMAC signature per-request. Verifikasi webhook juga jauh lebih
 * sederhana - header `X-Callback-Token` dibandingkan APA ADANYA (string
 * statis dari Xendit Dashboard), bukan HMAC atas body.
 */
class XenditService
{
    private const INVOICES_PATH = '/v2/invoices';

    private ?string $secretKey;

    private ?string $callbackToken;

    private string $baseUrl;

    public function __construct(?string $secretKey = null, ?string $callbackToken = null, ?string $baseUrl = null)
    {
        $this->secretKey = $secretKey ?? config('services.xendit.secret_key');
        $this->callbackToken = $callbackToken ?? config('services.xendit.callback_token');
        $this->baseUrl = $baseUrl ?? config('services.xendit.base_url');
    }

    /**
     * Buat Invoice Xendit, kembalikan ['url' => ..., 'id' => ..., 'expiry_date' => ...].
     * Ambil amount/externalId/currency sebagai parameter primitif (bukan
     * type-hint ke model Payment) supaya method ini bisa dipakai untuk
     * sumber transaksi apa pun - Payment (checkout sample klien) maupun
     * TokenPurchase (pembelian token grafolog) - sama seperti
     * DokuService::createCheckout() sebelumnya.
     *
     * @throws RuntimeException kalau XENDIT_SECRET_KEY belum diisi atau
     *                          Xendit menolak request - pesan asli Xendit diteruskan.
     */
    public function createInvoice(
        string $externalId,
        int $amount,
        string $currency,
        string $customerName,
        string $customerEmail,
    ): array {
        $this->ensureConfigured();

        $response = Http::withBasicAuth($this->secretKey, '')
            ->post($this->baseUrl.self::INVOICES_PATH, [
                'external_id' => $externalId,
                'amount' => $amount,
                'currency' => $currency,
                'payer_email' => $customerEmail,
                'description' => "Pembayaran Guratan untuk {$customerName} ({$externalId})",
                'success_redirect_url' => config('services.xendit.success_redirect_url'),
            ]);

        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->body();
            throw new RuntimeException('Xendit menolak permintaan pembayaran: '.$message);
        }

        return [
            'url' => $response->json('invoice_url'),
            'id' => $response->json('id'),
            'expiry_date' => $response->json('expiry_date'),
        ];
    }

    /**
     * Verifikasi header `X-Callback-Token` pada webhook yang dikirim
     * Xendit ke Callback URL kita. TIDAK melempar exception kalau belum
     * dikonfigurasi (beda dari createInvoice()) - endpoint webhook publik,
     * lebih aman selalu balas bool daripada berisiko 500 mentah kalau
     * env belum diisi.
     */
    public function verifyWebhookToken(Request $request): bool
    {
        if (! $this->callbackToken) {
            return false;
        }

        $incoming = $request->header('x-callback-token');

        if (! $incoming) {
            return false;
        }

        return hash_equals($this->callbackToken, $incoming);
    }

    private function ensureConfigured(): void
    {
        if (! $this->secretKey) {
            throw new RuntimeException(
                'XENDIT_SECRET_KEY belum diisi di .env - isi dulu dari Xendit Dashboard sebelum memproses pembayaran.'
            );
        }
    }
}
