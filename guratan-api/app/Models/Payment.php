<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'sample_id', 'invoice_number', 'amount', 'base_amount', 'discount_code_id',
        'currency', 'status', 'doku_token_id', 'doku_payment_url',
        'doku_payment_channel', 'notification_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'notification_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
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
