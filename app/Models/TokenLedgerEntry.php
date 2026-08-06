<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenLedgerEntry extends Model
{
    protected $fillable = ['user_id', 'type', 'delta', 'balance_after', 'report_id', 'token_purchase_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(PersonalityReport::class);
    }

    public function tokenPurchase(): BelongsTo
    {
        return $this->belongsTo(TokenPurchase::class);
    }
}
