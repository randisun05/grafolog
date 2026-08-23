<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user read state untuk Announcement - lihat AnnouncementController
 * untuk bagaimana ini dipakai membentuk `is_read`/`unread_count`.
 */
class AnnouncementRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
