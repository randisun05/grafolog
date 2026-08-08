<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndikatorCrossReference extends Model
{
    protected $table = 'indikator_cross_reference';

    protected $fillable = [
        'indikator_sumber_raw', 'indikator_sumber_id',
        'mereferensikan_ke_kode', 'match_status', 'aktif',
    ];

    protected $attributes = [
        'aktif' => true,
    ];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function indikatorSumber(): BelongsTo
    {
        return $this->belongsTo(Indikator::class, 'indikator_sumber_id');
    }
}
