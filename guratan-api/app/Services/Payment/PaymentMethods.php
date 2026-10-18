<?php

namespace App\Services\Payment;

use App\Models\DiscountCode;

/** Grup metode bayar + pembatasan oleh kode promo (config/payment_methods.php). */
class PaymentMethods
{
    /** @return array<int, array{key:string, label:string}> */
    public static function groups(): array
    {
        return collect(config('payment_methods'))->map(fn ($g, $k) => ['key' => $k, 'label' => $g['label']])->values()->all();
    }

    public static function keys(): array
    {
        return array_keys(config('payment_methods'));
    }

    /**
     * Daftar channel DOKU yang diizinkan di halaman bayar, atau null = tanpa batas.
     * Kode promo yang dibatasi ke grup tertentu: pembeli boleh memilih salah satunya (`$chosen`),
     * bila tidak memilih halaman bayar dibatasi ke seluruh grup yang diizinkan.
     * Tanpa batasan promo: `$chosen` (opsional) tetap dihormati sebagai preferensi pembeli.
     *
     * @return array<int, string>|null
     */
    public static function resolve(?DiscountCode $code, ?string $chosen): ?array
    {
        $allowed = $code?->payment_methods ?: null;
        if ($chosen !== null) {
            abort_unless(in_array($chosen, self::keys(), true), 422, 'Metode pembayaran tidak dikenal.');
            if ($allowed !== null && ! in_array($chosen, $allowed, true)) {
                abort(422, 'Kode promo ini hanya berlaku untuk metode: '.self::labels($allowed).'.');
            }

            return config("payment_methods.{$chosen}.types");
        }

        return $allowed === null ? null : collect($allowed)->flatMap(fn ($k) => config("payment_methods.{$k}.types", []))->values()->all();
    }

    public static function labels(array $keys): string
    {
        return collect($keys)->map(fn ($k) => config("payment_methods.{$k}.label", $k))->implode(', ');
    }
}
