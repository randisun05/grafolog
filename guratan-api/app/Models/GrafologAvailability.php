<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ketersediaan grafolog untuk satu bulan. `tersedia`/`terbatas` bisa dipilih HR; `penuh`/`libur` tidak. */
class GrafologAvailability extends Model
{
    public const STATUSES = ['tersedia', 'terbatas', 'penuh', 'libur'];

    public const BOOKABLE = ['tersedia', 'terbatas'];

    protected $fillable = ['user_id', 'month', 'status', 'capacity', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Ketersediaan efektif sebuah bulan (default tersedia) + sisa kuota dari beban aktif. */
    public static function forMonth(User $grafolog, string $month): array
    {
        $row = static::where('user_id', $grafolog->id)->where('month', $month)->first();
        $status = $row?->status ?? 'tersedia';
        $capacity = $row?->capacity;
        $workload = $grafolog->assignments()->where('status', 'assigned')->count()
            + $grafolog->serviceDeliveries()->whereIn('status', ['ditugaskan', 'dijadwalkan'])->count();
        $slotsLeft = $capacity === null ? null : max(0, $capacity - $workload);

        return [
            'month' => $month,
            'status' => $status,
            'capacity' => $capacity,
            'note' => $row?->note,
            'configured' => $row !== null,
            'workload' => $workload,
            'slots_left' => $slotsLeft,
            'bookable' => in_array($status, self::BOOKABLE, true) && ($slotsLeft === null || $slotsLeft > 0),
        ];
    }
}
