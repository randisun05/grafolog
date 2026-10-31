<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * Kontrak payment gateway (DOKU, Xendit). Pemanggil tidak peduli gateway mana yang
 * dipakai: cukup createCheckout() lalu simpan ['ref', 'url'] ke payments/token_purchases.
 */
interface PaymentGateway
{
    /** Kunci gateway ('doku' | 'xendit'), disimpan di kolom payments.gateway. */
    public function name(): string;

    /** Kredensial terisi (dipakai PaymentGatewayManager & PaymentMode). */
    public function isConfigured(): bool;

    /**
     * Buat sesi bayar. $methodTypes = channel yang diizinkan (hanya dihormati bila gateway
     * mendukung pembatasan; Xendit saat ini menampilkan semua metode).
     *
     * @return array{url: ?string, ref: ?string}
     *
     * @throws RuntimeException kredensial kosong / gateway menolak (pemanggil menjadikannya 503)
     */
    public function createCheckout(
        string $invoiceNumber,
        int $amount,
        string $currency,
        string $customerName,
        string $customerEmail,
        ?array $methodTypes = null,
    ): array;
}
