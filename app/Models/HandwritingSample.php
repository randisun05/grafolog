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
}
