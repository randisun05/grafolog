<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementCategory extends Model
{
    protected $table = 'measurement_category';

    protected $fillable = ['variable_id', 'kategori', 'rentang', 'unit', 'urutan'];

    public function variable(): BelongsTo
    {
        return $this->belongsTo(MeasurementVariable::class, 'variable_id');
    }
}
