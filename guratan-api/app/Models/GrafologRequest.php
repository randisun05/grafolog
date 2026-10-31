<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu permintaan penanganan pesanan klien ke seorang grafolog (jawab dalam 24 jam). */
class GrafologRequest extends Model
{
    public const WAITING = 'menunggu';

    public const ACCEPTED = 'diterima';

    public const DECLINED = 'ditolak';

    public const EXPIRED = 'kedaluwarsa';

    public const CANCELLED = 'dibatalkan';

    protected $fillable = ['order_id', 'grafolog_id', 'status', 'note', 'expires_at', 'responded_at', 'reminded_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }
}
