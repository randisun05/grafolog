<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'event', 'method', 'path', 'route', 'status', 'ip', 'user_agent'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
