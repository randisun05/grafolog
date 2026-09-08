<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monitoring pengiriman email/WhatsApp - lihat migrasi
 * `create_notification_logs_table` untuk penjelasan `status` (sent/
 * failed/skipped) dan `app/Services/NotificationDispatcher.php` untuk
 * di mana `record()` ini dipanggil.
 */
class NotificationLog extends Model
{
    protected $fillable = [
        'user_id', 'channel', 'type', 'recipient', 'status', 'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $channel,
        string $type,
        ?int $userId,
        string $recipient,
        string $status,
        ?string $errorMessage = null,
    ): self {
        return static::create([
            'user_id' => $userId,
            'channel' => $channel,
            'type' => $type,
            'recipient' => $recipient,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
