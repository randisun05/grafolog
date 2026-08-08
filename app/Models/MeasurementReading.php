<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementReading extends Model
{
    protected $table = 'measurement_readings';

    protected $fillable = ['sample_id', 'variable_id', 'nilai'];

    protected $casts = [
        'nilai' => 'float',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(MeasurementVariable::class, 'variable_id');
    }
}
