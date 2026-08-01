<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_user_id', 'aksi', 'target_type', 'target_id', 'ip_address',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function record(string $aksi, string $targetType, ?int $targetId, ?int $actorUserId, ?string $ipAddress): self
    {
        return static::create([
            'actor_user_id' => $actorUserId,
            'aksi' => $aksi,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $ipAddress,
        ]);
    }
}
