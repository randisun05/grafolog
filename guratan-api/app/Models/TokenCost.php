<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenCost extends Model
{
    protected $fillable = ['tier', 'tokens_required', 'is_active', 'updated_by'];

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

    public static function activeTokensFor(string $tier): ?int
    {
        return static::where('tier', $tier)->where('is_active', true)->value('tokens_required');
    }

    /**
     * Ganti jumlah token yang dibutuhkan untuk satu tier: nonaktifkan baris
     * lama (histori tetap ada), buat baris baru sebagai yang aktif. Pola
     * sama persis dengan PricingPlan::setPriceFor().
     */
    public static function setTokensFor(string $tier, int $tokensRequired, User $updatedBy): self
    {
        static::where('tier', $tier)->where('is_active', true)->update(['is_active' => false]);

        return static::create([
            'tier' => $tier,
            'tokens_required' => $tokensRequired,
            'is_active' => true,
            'updated_by' => $updatedBy->id,
        ]);
    }
}
