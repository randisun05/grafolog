<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRevision extends Model
{
    protected $fillable = ['report_id', 'jenis', 'data', 'catatan', 'actor_user_id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(PersonalityReport::class, 'report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
