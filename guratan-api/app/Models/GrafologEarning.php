<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Komisi (Rp) yang diperoleh grafolog dari satu pesanan selesai. Snapshot aturan saat dihitung. */
class GrafologEarning extends Model
{
    public const EARNED = 'diperoleh';

    public const PAID = 'dibayar';

    public const CANCELLED = 'dibatalkan';

    protected $fillable = [
        'grafolog_id', 'order_id', 'product_id', 'base_amount', 'commission_type', 'commission_value', 'amount',
        'status', 'payout_id', 'note', 'earned_at',
    ];

    protected function casts(): array
    {
        return ['earned_at' => 'datetime'];
    }

    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
