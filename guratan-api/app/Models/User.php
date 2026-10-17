<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'phone', 'password', 'role', 'company_id', 'token_balance', 'consented_at', 'consent_source', 'consent_version',
    'acquisition_source', 'referred_by', 'grafolog_status', 'certificate_number', 'certificate_path',
    'grafolog_verified_at', 'grafolog_verified_by', 'grafolog_review_note',
])]
#[Hidden(['password', 'remember_token', 'certificate_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const CONSENT_VERSION = '2026-10';

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

    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isHr(): bool
    {
        return $this->role === 'hr';
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
            'password' => 'hashed',
        ];
    }
}
