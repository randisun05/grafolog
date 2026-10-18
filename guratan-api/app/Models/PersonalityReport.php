<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalityReport extends Model
{
    protected $fillable = [
        'sample_id', 'tier', 'status', 'data', 'pdf_path', 'generated_at',
        'review_status', 'reviewed_by', 'reviewed_at', 'review_note',
        'validation', 'validation_requested_at', 'validation_fee', 'validation_paid_to', 'validation_paid_at',
    ];

    /** Fase D (2026-10-17): laporan baru menunggu keputusan grafolog - terbit mandiri / minta validasi. */
    public const REVIEW_DRAFT = 'draf';

    public const VALIDATION_SELF = 'mandiri';

    public const VALIDATION_SUPERVISOR = 'supervisor';

    public const REVIEW_PENDING = 'menunggu_review';

    public const REVIEW_APPROVED = 'disetujui';

    public const REVIEW_REVISION = 'perlu_revisi';

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'validation_requested_at' => 'datetime',
            'validation_paid_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->review_status === self::REVIEW_APPROVED;
    }

    /**
     * QA supervisor (2026-10-03): klien & HR hanya melihat laporan yang sudah
     * disetujui. Grafolog penanganan, supervisor, dan administrator melihat
     * di semua tahap review.
     */
    public function isVisibleTo(User $user): bool
    {
        if (! $this->sample->isViewableBy($user)) {
            return false;
        }

        return $this->isApproved()
            || $user->isAdministrator()
            || $this->isValidatedBy($user)
            || ($user->isGrafolog() && $this->sample->isScorableBy($user));
    }

    /**
     * Setiap perubahan isi laporan oleh grafolog mengembalikannya ke antrean
     * review supaya klien tidak pernah melihat isi yang belum ditinjau.
     */
    public function sendBackToReview(): void
    {
        // Isi berubah -> validasi/terbitan sebelumnya tidak berlaku lagi. Grafolog memutuskan ulang
        // (terbit mandiri atau minta validasi baru dengan biaya baru).
        $this->update([
            'review_status' => self::REVIEW_DRAFT,
            'validation' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ReportValidation::class, 'report_id');
    }

    public function activeValidation(): ?ReportValidation
    {
        return $this->validations()->whereIn('status', ReportValidation::ACTIVE)->latest('id')->first();
    }

    public function hasActiveValidation(): bool
    {
        return $this->validations()->whereIn('status', ReportValidation::ACTIVE)->exists();
    }

    /** Validator yang ditugaskan (belum menolak/kedaluwarsa) boleh membaca laporan & sampelnya. */
    public function isValidatedBy(User $user): bool
    {
        return $this->validations()->where('validator_id', $user->id)
            ->whereIn('status', [...ReportValidation::ACTIVE, ReportValidation::DONE])->exists();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(HandwritingSample::class, 'sample_id');
    }

    public function aspekScores(): HasMany
    {
        return $this->hasMany(ReportAspekScore::class, 'report_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReportRevision::class, 'report_id');
    }
}
