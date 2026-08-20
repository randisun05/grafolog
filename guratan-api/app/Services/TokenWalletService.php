<?php

namespace App\Services;

use App\Models\TokenLedgerEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang boleh mengubah User::token_balance - setiap
 * perubahan mengunci baris user (lockForUpdate) dan mencatat satu baris
 * token_ledger_entries di transaksi yang sama, supaya balance_after selalu
 * konsisten dengan cache di kolom users.token_balance meski ada dua
 * request bersamaan (mis. grafolog submit dua laporan sekaligus).
 */
class TokenWalletService
{
    /**
     * Tambah saldo (dari pembelian token sukses).
     */
    public function credit(User $user, int $amount, array $refs = []): TokenLedgerEntry
    {
        return DB::transaction(function () use ($user, $amount, $refs) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            $newBalance = $locked->token_balance + $amount;
            $locked->forceFill(['token_balance' => $newBalance])->save();

            return TokenLedgerEntry::create([
                'user_id' => $user->id,
                'type' => 'purchase',
                'delta' => $amount,
                'balance_after' => $newBalance,
                ...$refs,
            ]);
        });
    }

    /**
     * Potong saldo (dari laporan yang berhasil dibuat). Melempar 402 kalau
     * saldo tidak cukup - dipanggil di dalam DB::transaction milik
     * ScoringController::submit, jadi kalau ini gagal seluruh pembuatan
     * laporan ikut rollback (laporan tidak pernah setengah jadi).
     */
    public function debit(User $user, int $amount, array $refs = []): TokenLedgerEntry
    {
        return DB::transaction(function () use ($user, $amount, $refs) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            abort_if($locked->token_balance < $amount, 402, 'Token Anda tidak cukup untuk membuat laporan ini. Silakan beli token terlebih dahulu.');

            $newBalance = $locked->token_balance - $amount;
            $locked->forceFill(['token_balance' => $newBalance])->save();

            return TokenLedgerEntry::create([
                'user_id' => $user->id,
                'type' => 'consumption',
                'delta' => -$amount,
                'balance_after' => $newBalance,
                ...$refs,
            ]);
        });
    }
}
