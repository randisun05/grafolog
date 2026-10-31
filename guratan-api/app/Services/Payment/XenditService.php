<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integrasi Xendit Invoices (https://docs.xendit.co/docs/invoice-overview), gateway ALTERNATIF
 * di samping DOKU. Kontrak API diambil dari implementasi di branch lama (dicek ke dokumentasi
 * Xendit 2026-09-20): satu base URL untuk sandbox & produksi (mode ditentukan prefix secret key,
 * `xnd_development_` vs `xnd_production_`), auth HTTP Basic (secret key sebagai username), dan
 * webhook diverifikasi dengan header `X-Callback-Token` statis dari Xendit Dashboard.
 * Belum pernah dicoba terhadap Xendit sungguhan: kirim satu transaksi test dan cocokkan
 * `notification_payload` yang tersimpan sebelum dipakai di produksi.
 */
class XenditService implements PaymentGateway
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

    public function name(): string
    {
        return 'xendit';
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey);
    }

    public function createCheckout(
        string $invoiceNumber,
        int $amount,
        string $currency,
        string $customerName,
        string $customerEmail,
        ?array $methodTypes = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('XENDIT_SECRET_KEY belum diisi di .env - isi dulu dari Xendit Dashboard sebelum memproses pembayaran.');
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(20)
                ->post($this->baseUrl.self::INVOICES_PATH, [
                    'external_id' => $invoiceNumber,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payer_email' => $customerEmail,
                    'description' => "Pembayaran Guratan untuk {$customerName} ({$invoiceNumber})",
                    'success_redirect_url' => config('services.xendit.success_redirect_url'),
                ]);
        } catch (HttpClientException $e) {
            throw new RuntimeException('Gagal menghubungi Xendit: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Xendit menolak permintaan pembayaran: '.($response->json('message') ?? $response->body()));
        }

        return ['url' => $response->json('invoice_url'), 'ref' => $response->json('id')];
    }

    /** Selalu bool (tidak melempar) - endpoint webhook publik, jangan 500 bila env kosong. */
    public function verifyWebhookToken(Request $request): bool
    {
        $incoming = $request->header('x-callback-token');

        return filled($this->callbackToken) && filled($incoming) && hash_equals($this->callbackToken, $incoming);
    }
}
