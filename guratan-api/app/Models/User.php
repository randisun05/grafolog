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

#[Fillable(['name', 'email', 'password', 'role', 'company_id', 'token_balance', 'consented_at', 'consent_source', 'consent_version'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const CONSENT_VERSION = '2026-10';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isGrafolog(): bool
    {
        return $this->role === 'grafolog';
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
            'password' => 'hashed',
        ];
    }
}
