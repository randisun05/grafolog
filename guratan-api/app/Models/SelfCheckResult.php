<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfCheckResult extends Model
{
    protected $fillable = ['uuid', 'user_id', 'email', 'consented_at', 'answers', 'aspek_ids'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'aspek_ids' => 'array', 'consented_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
