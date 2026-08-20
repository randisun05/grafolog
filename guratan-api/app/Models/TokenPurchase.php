<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenPurchase extends Model
{
    protected $fillable = [
        'user_id', 'quantity', 'unit_price', 'base_amount', 'discount_code_id',
        'amount', 'currency', 'status', 'invoice_number', 'doku_token_id',
        'doku_payment_url', 'notification_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
