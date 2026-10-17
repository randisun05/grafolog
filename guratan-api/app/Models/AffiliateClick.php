<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AffiliateClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['affiliate_product_id', 'user_id'];
}
