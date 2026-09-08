<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HandwritingSample extends Model
{
    protected $fillable = [
        'project_id', 'user_id', 'created_by', 'image_path', 'tier', 'status',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PersonalityReport::class, 'sample_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'sample_id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class, 'sample_id');
    }

    public function measurementReadings(): HasMany
    {
        return $this->hasMany(MeasurementReading::class, 'sample_id');
    }

    public function indikatorChecks(): HasMany
    {
        return $this->hasMany(SampleIndikatorCheck::class, 'sample_id');
    }

    /**
     * MGA Fase 06: a sample can be scored either by the grafolog who
     * created it directly (the original flow) OR by whoever an HR/admin
     * explicitly assigned it to (the new HR flow) - additive, doesn't
     * replace the original check.
     */
    public function isScorableBy(User $user): bool
    {
        return $this->created_by === $user->id
            || $this->assignment?->grafolog_id === $user->id;
    }

    /**
     * Same additive pattern as isScorableBy(), for read access
     * (SampleController::show / ReportController).
     *
     * 2026-09-08 (fitur Supervisor): tambahan aditif ke-2 - Supervisor
     * company-scoped bisa lihat sample manapun yang dibuat oleh akun HR
     * di company yang sama (rantai Project.creator.company_id, sama
     * seperti Company::sampleIds()). Sample dari sumber lain (grafolog
     * langsung, checkout mandiri klien) punya `project.creator.company_id`
     * null - otomatis TIDAK PERNAH cocok dengan company Supervisor manapun,
     * tidak perlu guard tambahan untuk membedakan sumber.
     */
    public function isViewableBy(User $user): bool
    {
        if ($this->user_id === $user->id
            || $this->created_by === $user->id
            || $this->assignment?->grafolog_id === $user->id) {
            return true;
        }

        return $user->isSupervisor()
            && $user->company_id !== null
            && $this->project?->creator?->company_id === $user->company_id;
    }

    /**
     * Query-level versi dari isViewableBy() - dipakai SampleController::index()
     * dan ReportController::index() (via whereHas('sample', ...)) supaya
     * klausa OR yang sebelumnya ter-duplikasi identik di kedua tempat itu
     * jadi satu sumber kebenaran.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('assignment', fn ($a) => $a->where('grafolog_id', $user->id));

            if ($user->isSupervisor() && $user->company_id !== null) {
                $q->orWhereHas('project.creator', fn ($c) => $c->where('company_id', $user->company_id));
            }
        });
    }

    /**
     * Commerce inisiatif (2026-08-06): samples dari checkout mandiri klien
     * (Project.source === 'client') harus lunas dulu sebelum bisa di-skor -
     * endpoint pembuatan sample-nya (POST /api/samples) sudah bisa dipanggil
     * klien sejak Fase 02 tapi TIDAK PERNAH dicek pembayarannya sampai
     * sekarang. Sample dari grafolog-langsung ('grafolog') dan HR-impor
     * ('hr') sengaja TIDAK kena gate ini - diasumsikan ada pengaturan
     * pembayaran terpisah (invoice B2B/manual), bukan per-sample.
     */
    public function requiresPayment(): bool
    {
        return $this->project?->source === 'client';
    }

    public function isPaid(): bool
    {
        return $this->payments()->where('status', 'paid')->exists();
    }
}
