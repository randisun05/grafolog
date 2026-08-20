<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementReading extends Model
{
    protected $table = 'measurement_readings';

    protected $fillable = ['sample_id', 'variable_id', 'nilai', 'nilai_min', 'nilai_max'];

    protected $casts = [
        'nilai' => 'float',
        'nilai_min' => 'float',
        'nilai_max' => 'float',
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
