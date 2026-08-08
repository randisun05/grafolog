<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SampleIndikatorCheck extends Model
{
    protected $table = 'sample_indikator_checks';

    protected $fillable = [
        'sample_id', 'indikator_id', 'checked', 'sumber', 'rule_id', 'cross_reference_id', 'keterangan_pemicu',
    ];

    protected $attributes = [
        'checked' => true,
    ];

    protected $casts = [
        'checked' => 'boolean',
    ];

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(Indikator::class, 'indikator_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IndikatorRule::class, 'rule_id');
    }

    public function crossReference(): BelongsTo
    {
        return $this->belongsTo(IndikatorCrossReference::class, 'cross_reference_id');
    }
}
