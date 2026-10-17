<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Posisi lowongan milik perusahaan. `key_aspek_ids` = aspek yang HR anggap paling relevan untuk
 * posisi ini - hanya menentukan kolom yang ditonjolkan di analisis kandidat, BUKAN skor kecocokan.
 */
class JobPosition extends Model
{
    protected $fillable = ['company_id', 'title', 'description', 'key_aspek_ids', 'is_active', 'created_by'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['key_aspek_ids' => 'array', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(CandidateProfile::class);
    }
}
