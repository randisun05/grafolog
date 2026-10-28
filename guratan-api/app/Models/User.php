<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'phone', 'password', 'role', 'company_id', 'token_balance', 'consented_at', 'consent_source', 'consent_version',
    'acquisition_source', 'referred_by', 'grafolog_status', 'certificate_number', 'certificate_path',
    'grafolog_verified_at', 'grafolog_verified_by', 'grafolog_review_note',
    'must_change_password', 'email_notifications', 'anonymized_at', 'city', 'bio', 'specializations', 'experience_years', 'birth_date', 'gender', 'education', 'occupation',
])]
#[Hidden(['password', 'remember_token', 'certificate_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const CONSENT_VERSION = '2026-10-19';

    /** Jenis pemberitahuan yang tetap dikirim lewat email walau pengguna mematikan email notifikasi. */
    public const ESSENTIAL_NOTICE_TYPES = ['pembayaran', 'refund', 'verifikasi', 'insiden'];

    protected $appends = ['needs_consent'];

    /**
     * Pengguna perlu menyetujui ulang ToS/Privasi bila versi persetujuannya bukan yang berlaku,
     * atau persetujuannya dicatatkan pihak lain (HR untuk kandidat) dan belum ia terima sendiri.
     */
    protected function needsConsent(): Attribute
    {
        return Attribute::get(fn () => $this->consent_version !== self::CONSENT_VERSION || $this->consent_source === 'hr');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Grafolog yang BOLEH bertindak sebagai grafolog. Pendaftar mandiri berstatus
     * `pending` sampai admin memverifikasi (2026-10-17) - selama itu semua cek
     * isGrafolog() (lookup klien, buat sample, skoring, token) menolak. null =
     * akun lama/dibuat admin, dianggap terverifikasi.
     */
    public function isGrafolog(): bool
    {
        return $this->role === 'grafolog' && in_array($this->grafolog_status, [null, 'verified'], true);
    }

    public function isPendingGrafolog(): bool
    {
        return $this->role === 'grafolog' && ! $this->isGrafolog();
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    public function isAdministrator(): bool
    {
        return $this->role === 'administrator';
    }

    public function samples(): HasMany
    {
        return $this->hasMany(HandwritingSample::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    /** HR yang perusahaannya belum diverifikasi admin (pendaftaran B2B mandiri, 2026-10-17). */
    public function isPendingHr(): bool
    {
        return $this->role === 'hr' && $this->company && ! $this->company->isVerified();
    }

    /** Penugasan sampel ke grafolog ini. */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'grafolog_id');
    }

    /** Layanan pendukung yang ditangani grafolog ini. */
    public function serviceDeliveries(): HasMany
    {
        return $this->hasMany(ServiceDelivery::class, 'assigned_to');
    }

    public function candidateProfiles(): HasMany
    {
        return $this->hasMany(CandidateProfile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consented_at' => 'datetime',
            'grafolog_verified_at' => 'datetime',
            'specializations' => 'array',
            'birth_date' => 'date:Y-m-d',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'email_notifications' => 'boolean',
            'anonymized_at' => 'datetime',
        ];
    }
}
