<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil kandidat per perusahaan (2026-10-17). Identitas kandidat tetap `User` role user (lihat
 * CLAUDE.md "Candidate is intentionally not a new table") - tabel ini hanya data lamaran.
 */
class CandidateProfile extends Model
{
    public const STAGES = ['baru', 'asesmen', 'selesai', 'lanjut', 'tidak_lanjut'];

    protected $fillable = [
        'user_id', 'company_id', 'job_position_id', 'education', 'experience_years', 'notes', 'stage', 'created_by',
    ];

    protected $attributes = ['stage' => 'baru'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }
}
