<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesi konsultasi tier Master: grafolog menetapkan jadwal + tautan meeting
 * daring (Zoom/Google Meet), klien melihatnya di laporan & menerima email.
 */
class SampleConsultation extends Model
{
    protected $attributes = ['status' => 'dijadwalkan'];

    protected $fillable = ['sample_id', 'scheduled_at', 'meeting_url', 'notes', 'status', 'scheduled_by'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }
}
