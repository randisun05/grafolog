<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perusahaan klien B2B. Sejak 2026-10-17 perusahaan bisa mendaftar sendiri (status `pending`
 * sampai admin memverifikasi); yang dibuat admin langsung `verified`.
 */
class Company extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'status', 'industry', 'employee_range', 'phone', 'address', 'website',
        'verified_at', 'verified_by', 'review_note', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_VERIFIED];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(JobPosition::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(CandidateProfile::class);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}
