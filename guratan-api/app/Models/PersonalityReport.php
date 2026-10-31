<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonalityReport extends Model
{
    protected $fillable = [
        'sample_id', 'tier', 'status', 'data', 'pdf_path', 'generated_at',
        'review_status', 'reviewed_by', 'reviewed_at', 'review_note',
        'validation', 'validation_requested_at', 'validation_fee', 'validation_paid_to', 'validation_paid_at',
        'narasi_terpadu', 'narasi_bahasa', 'narasi_status', 'pdf_path_klien',
        'narasi_input_hash', 'narasi_generation_error',
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

    /**
     * Rata-rata durasi pengerjaan (sample.created_at -> report.generated_at)
     * dalam hari, untuk sekumpulan sample_id manapun (per-user, per-company,
     * per-grafolog, dst - pemanggil yang tentukan lingkupnya). Diekstrak
     * 2026-09-03 dari duplikasi byte-identik di DashboardController dan
     * Admin\CompanyController (2 tempat, akan jadi 3-4 dengan fitur rekap -
     * di titik itu ekstraksi ke model, bukan controller lain, jadi masuk
     * akal - lihat guratan-api/CLAUDE.md).
     */
    public static function avgTurnaroundDaysFor(Collection $sampleIds): ?float
    {
        $reports = self::whereIn('sample_id', $sampleIds)
            ->where('status', 'completed')
            ->whereNotNull('generated_at')
            ->with('sample:id,created_at')
            ->get();

        if ($reports->isEmpty()) {
            return null;
        }

        $totalDays = $reports->sum(
            fn (self $report) => $report->sample->created_at->diffInHours($report->generated_at) / 24
        );

        return round($totalDays / $reports->count(), 1);
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
