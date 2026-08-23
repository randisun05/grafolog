<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Integrasi DOKU Checkout (https://developers.doku.com/accept-payments/doku-checkout).
 * Skema signature (Digest + HMAC-SHA256) diverifikasi dari dokumentasi resmi
 * DOKU 2026-07-27 - JANGAN diubah tanpa mengecek ulang ke developers.doku.com,
 * signature yang salah gagal otentikasi di sisi DOKU tanpa pesan yang jelas.
 */
class DokuService
{
    private const CHECKOUT_PATH = '/checkout/v1/payment';

    private ?string $clientId;

    private ?string $secretKey;

    private ?string $baseUrl;

    public function __construct(?string $clientId = null, ?string $secretKey = null, ?string $baseUrl = null)
    {
        $this->clientId = $clientId ?? config('services.doku.client_id');
        $this->secretKey = $secretKey ?? config('services.doku.secret_key');
        $this->baseUrl = $baseUrl ?? config('services.doku.base_url');
    }

    /**
     * Buat sesi pembayaran DOKU Checkout, kembalikan
     * ['url' => ..., 'token_id' => ..., 'expired_date' => ...]. Ambil
     * amount/invoice_number/currency sebagai parameter primitif (bukan
     * type-hint ke model Payment) supaya method ini bisa dipakai untuk
     * sumber transaksi apa pun - Payment (checkout sample klien) maupun
     * TokenPurchase (pembelian token grafolog) - tanpa DokuService perlu
     * tahu bentuk model pemanggilnya.
     *
     * @throws RuntimeException kalau DOKU menolak request (kredensial belum
     *                          diisi, invoice_number dobel, dsb) - pesan asli DOKU diteruskan.
     */
    public function createCheckout(
        string $invoiceNumber,
        int $amount,
        string $currency,
        string $customerName,
        string $customerEmail,
    ): array {
        $this->ensureConfigured();

        $body = [
            'order' => [
                'amount' => $amount,
                'invoice_number' => $invoiceNumber,
                'currency' => $currency,
                'auto_redirect' => true,
                'callback_url' => config('services.doku.callback_url', config('app.frontend_url')),
            ],
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
            ],
        ];

        $response = $this->post(self::CHECKOUT_PATH, $body);

        if (! $response->successful()) {
            $messages = $response->json('error_messages') ?? $response->json('message') ?? [$response->body()];
            throw new RuntimeException('DOKU menolak permintaan pembayaran: '.implode('; ', (array) $messages));
        }

        $payload = $response->json('response');

        return [
            'url' => $payload['payment']['url'] ?? null,
            'token_id' => $payload['payment']['token_id'] ?? null,
            'expired_date' => $payload['payment']['expired_date'] ?? null,
        ];
    }

    /**
     * Kirim POST ke endpoint DOKU dengan header Client-Id/Request-Id/
     * Request-Timestamp/Signature yang benar.
     */
    private function post(string $path, array $body)
    {
        $requestId = (string) Str::uuid();
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $rawBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        $digest = $this->digest($rawBody);
        $signature = $this->generateSignature($requestId, $timestamp, $path, $digest);

        return Http::withHeaders([
            'Client-Id' => $this->clientId,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => 'HMACSHA256='.$signature,
            'Content-Type' => 'application/json',
        ])->withBody($rawBody, 'application/json')
            ->post($this->baseUrl.$path);
    }

    /**
     * Verifikasi Signature header pada notifikasi (webhook) yang dikirim
     * DOKU ke Notification URL kita. Body HARUS raw string persis seperti
     * yang diterima (jangan re-encode dari array) supaya digest cocok.
     */
    public function verifyNotificationSignature(Request $request): bool
    {
        $this->ensureConfigured();

        $clientId = $request->header('Client-Id');
        $requestId = $request->header('Request-Id');
        $timestamp = $request->header('Request-Timestamp');
        $incomingSignature = $request->header('Signature');

        if (! $clientId || ! $requestId || ! $timestamp || ! $incomingSignature) {
            return false;
        }

        $digest = $this->digest($request->getContent());
        $expected = 'HMACSHA256='.$this->generateSignature(
            $requestId,
            $timestamp,
            $request->path() === '/' ? '/' : '/'.ltrim($request->path(), '/'),
            $digest,
            $clientId,
        );

        return hash_equals($expected, $incomingSignature);
    }

    private function digest(string $rawBody): string
    {
        return base64_encode(hash('sha256', $rawBody, true));
    }

    private function generateSignature(
        string $requestId,
        string $timestamp,
        string $requestTarget,
        string $digest,
        ?string $clientId = null,
    ): string {
        $component = implode("\n", [
            'Client-Id:'.($clientId ?? $this->clientId),
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$requestTarget,
            'Digest:'.$digest,
        ]);

        return base64_encode(hash_hmac('sha256', $component, $this->secretKey, true));
    }

    private function ensureConfigured(): void
    {
        if (! $this->clientId || ! $this->secretKey) {
            throw new RuntimeException(
                'DOKU_CLIENT_ID / DOKU_SECRET_KEY belum diisi di .env - isi dulu dari DOKU Back Office sebelum memproses pembayaran.'
            );
        }
    }
}
