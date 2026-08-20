<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPlan extends Model
{
    protected $fillable = ['tier', 'price', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function activePriceFor(string $tier): ?int
    {
        return static::where('tier', $tier)->where('is_active', true)->value('price');
    }

    /**
     * Ganti harga aktif untuk satu tier: nonaktifkan baris lama (histori
     * tetap ada, tidak dihapus), buat baris baru sebagai yang aktif.
     */
    public static function setPriceFor(string $tier, int $price, User $updatedBy): self
    {
        static::where('tier', $tier)->where('is_active', true)->update(['is_active' => false]);

        return static::create([
            'tier' => $tier,
            'price' => $price,
            'is_active' => true,
            'updated_by' => $updatedBy->id,
        ]);
    }
}
