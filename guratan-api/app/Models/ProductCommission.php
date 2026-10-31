<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Override komisi grafolog tertentu untuk produk tertentu (menggantikan komisi default produk). */
class ProductCommission extends Model
{
    protected $fillable = ['product_id', 'grafolog_id', 'commission_type', 'commission_value'];

    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }
}
