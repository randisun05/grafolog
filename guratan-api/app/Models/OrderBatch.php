<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pesanan B2B (2026-10-17): satu produk untuk banyak kandidat, dibayar sekali. Tiap kandidat
 * tetap punya `Order` sendiri (batch_id) supaya alur layanan & laporan sama dengan B2C.
 */
class OrderBatch extends Model
{
    protected $fillable = [
        'company_id', 'product_id', 'grafolog_id', 'status', 'base_amount', 'amount',
        'discount_code_id', 'created_by', 'paid_at',
    ];

    protected $attributes = ['status' => Order::STATUS_UNPAID];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'batch_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
