<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ketersediaan grafolog untuk satu bulan (jadwal setahun, 2026-10-18). `tersedia`/`terbatas`
 * bisa dipesan HR; `penuh`/`libur` tidak. `supervision` = bersedia menerima penugasan supervisi
 * laporan sesama grafolog bulan itu. Bulan tanpa baris: tersedia untuk dipesan, TIDAK bersedia supervisi.
 */
class GrafologAvailability extends Model
{
    public const STATUSES = ['tersedia', 'terbatas', 'penuh', 'libur'];

    public const BOOKABLE = ['tersedia', 'terbatas'];

    public const MONTHS_AHEAD = 11; // bulan ini + 11 = 12 bulan

    protected $fillable = ['user_id', 'month', 'status', 'capacity', 'supervision', 'note'];

    protected function casts(): array
    {
        return ['supervision' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<int, string> daftar 'YYYY-MM' jendela setahun mulai bulan ini */
    public static function window(): array
    {
        return collect(range(0, self::MONTHS_AHEAD))
            ->map(fn ($i) => now()->startOfMonth()->addMonths($i)->format('Y-m'))->all();
    }

    /**
     * Ketersediaan efektif sebuah bulan. Kuota dihitung dari kandidat pesanan B2B yang memilih
     * grafolog ini untuk bulan layanan tersebut (belum dibatalkan/dikembalikan).
     */
    public static function forMonth(User $grafolog, string $month): array
    {
        $row = static::where('user_id', $grafolog->id)->where('month', $month)->first();
        $status = $row?->status ?? 'tersedia';
        $capacity = $row?->capacity;
        $workload = Order::whereHas('batch', fn ($q) => $q->where('grafolog_id', $grafolog->id)->where('service_month', $month))
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])->count();
        $slotsLeft = $capacity === null ? null : max(0, $capacity - $workload);
        $statusOk = in_array($status, self::BOOKABLE, true);

        return [
            'month' => $month,
            'status' => $status,
            'capacity' => $capacity,
            'note' => $row?->note,
            'supervision' => (bool) $row?->supervision,
            'configured' => $row !== null,
            'workload' => $workload,
            'slots_left' => $slotsLeft,
            'bookable' => $statusOk && ($slotsLeft === null || $slotsLeft > 0),
            'supervisable' => $statusOk && (bool) $row?->supervision,
        ];
    }
}
