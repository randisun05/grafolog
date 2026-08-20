<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class DiscountCode extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'applicable_tiers', 'max_uses',
        'used_count', 'valid_from', 'valid_until', 'is_active', 'created_by',
    ];

    /**
     * Mirror the DB defaults here too - MySQL doesn't refetch default
     * column values after INSERT, so without this the in-memory model
     * right after create() has is_active/used_count as null instead of
     * the DB's true/0 until you ->fresh() it.
     */
    protected $attributes = [
        'is_active' => true,
        'used_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'applicable_tiers' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cek semua syarat kode diskon SEKALIGUS (aktif, jendela waktu, kuota,
     * berlaku untuk tier ini) - dipanggil dari endpoint preview & (nanti)
     * checkout, jangan cek sebagian syarat saja di satu tempat.
     */
    public function isValidFor(string $tier): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();
        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->applicable_tiers !== null && ! in_array($tier, $this->applicable_tiers, true)) {
            return false;
        }

        return true;
    }

    /**
     * Nominal potongan dalam Rupiah untuk satu harga dasar - tidak pernah
     * melebihi harga itu sendiri (harga akhir minimal 0, bukan negatif).
     */
    public function amountOff(int $basePrice): int
    {
        $amount = $this->type === 'percentage'
            ? (int) round($basePrice * $this->value / 100)
            : $this->value;

        return min($amount, $basePrice);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
