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

#[Fillable(['name', 'email', 'password', 'role', 'company_id', 'token_balance', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // is_active punya default DB true, tapi MySQL tidak refetch itu ke model
    // in-memory setelah create() tanpa ini - gotcha yang sama seperti
    // DiscountCode/Announcement, lihat guratan-api/CLAUDE.md.
    protected $attributes = [
        'is_active' => true,
    ];

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
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
