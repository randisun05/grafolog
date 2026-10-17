<?php

namespace App\Services\Payment;

/**
 * Mode uji pembayaran (2026-10-17): selama akun DOKU belum ada, checkout klien
 * dan pembelian token langsung ditandai lunas tanpa memanggil DOKU, supaya
 * alur setelah bayar (penugasan grafolog, skor, laporan, saldo token) bisa
 * diuji manual. TIDAK PERNAH aktif di production, apa pun isi env-nya.
 */
class PaymentMode
{
    public static function bypassed(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        $explicit = config('payment.bypass');
        if ($explicit !== null && $explicit !== '') {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }

        return blank(config('services.doku.client_id'));
    }
}
