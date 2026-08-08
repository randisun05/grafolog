<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorRule extends Model
{
    protected $table = 'indikator_rules';

    protected $fillable = [
        'indikator_id', 'rule_type', 'variable_a_id', 'category_label',
        'operator', 'koefisien', 'variable_b_id', 'compare_value',
    ];

    protected $attributes = [
        'koefisien' => 1.0,
    ];

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(Indikator::class, 'indikator_id');
    }

    public function variableA(): BelongsTo
    {
        return $this->belongsTo(MeasurementVariable::class, 'variable_a_id');
    }

    public function variableB(): BelongsTo
    {
        return $this->belongsTo(MeasurementVariable::class, 'variable_b_id');
    }
}
