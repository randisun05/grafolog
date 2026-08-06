<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenPrice extends Model
{
    protected $fillable = ['price_per_token', 'is_active', 'updated_by'];

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

    public static function current(): ?int
    {
        return static::where('is_active', true)->value('price_per_token');
    }

    /**
     * Ganti harga per token yang aktif: nonaktifkan baris lama (histori
     * tetap ada), buat baris baru sebagai yang aktif. Pola sama persis
     * dengan PricingPlan::setPriceFor().
     */
    public static function setPrice(int $pricePerToken, User $updatedBy): self
    {
        static::where('is_active', true)->update(['is_active' => false]);

        return static::create([
            'price_per_token' => $pricePerToken,
            'is_active' => true,
            'updated_by' => $updatedBy->id,
        ]);
    }
}
