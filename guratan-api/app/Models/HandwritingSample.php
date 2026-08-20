<?php

namespace App\Models;

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
     */
    public function isViewableBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->created_by === $user->id
            || $this->assignment?->grafolog_id === $user->id;
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
