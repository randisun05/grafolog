<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAspekScore extends Model
{
    protected $fillable = [
        'report_id', 'aspek_id', 'skor', 'catatan_grafolog',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(PersonalityReport::class, 'report_id');
    }

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(Aspek::class, 'aspek_id');
    }
}
