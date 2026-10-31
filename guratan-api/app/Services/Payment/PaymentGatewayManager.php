<?php

namespace App\Services\Payment;

use Illuminate\Validation\ValidationException;

/**
 * Memilih gateway: default dari config('payment.gateway'), pembeli boleh memilih salah satu
 * gateway yang kredensialnya terisi (request field `gateway`).
 */
class PaymentGatewayManager
{
    public const GATEWAYS = ['doku' => DokuService::class, 'xendit' => XenditService::class];

    public function driver(string $name): PaymentGateway
    {
        return app(self::GATEWAYS[$name] ?? self::GATEWAYS['doku']);
    }

    /**
     * Gateway yang kredensialnya terisi, default di urutan pertama.
     *
     * @return array<int, string>
     */
    public function available(): array
    {
        $names = array_values(array_filter(array_keys(self::GATEWAYS), fn ($n) => $this->driver($n)->isConfigured()));
        $default = $this->defaultName();
        usort($names, fn ($a, $b) => ($b === $default) <=> ($a === $default));

        return $names;
    }

    public function defaultName(): string
    {
        $name = config('payment.gateway', 'doku');

        return isset(self::GATEWAYS[$name]) ? $name : 'doku';
    }

    /** Gateway untuk transaksi ini: pilihan pembeli bila valid, selain itu default. */
    public function resolve(?string $chosen): PaymentGateway
    {
        if ($chosen === null || $chosen === '') {
            return $this->driver($this->defaultName());
        }
        if (! isset(self::GATEWAYS[$chosen])) {
            throw ValidationException::withMessages(['gateway' => 'Gateway pembayaran tidak dikenal.']);
        }
        if (! $this->driver($chosen)->isConfigured()) {
            throw ValidationException::withMessages(['gateway' => 'Gateway pembayaran ini belum tersedia.']);
        }

        return $this->driver($chosen);
    }

    /** Mode uji (bypass) hanya bila TIDAK ada gateway yang terkonfigurasi. */
    public function anyConfigured(): bool
    {
        return $this->available() !== [];
    }
}
