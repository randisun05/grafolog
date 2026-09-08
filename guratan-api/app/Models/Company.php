<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Company extends Model
{
    protected $fillable = ['name', 'created_by', 'is_active'];

    // Gotcha DB-default-tidak-refetch yang sama seperti User/DiscountCode/
    // Announcement - lihat guratan-api/CLAUDE.md.
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CompanyContract::class);
    }

    /**
     * ID akun HR (role=hr) milik company ini - bisa lebih dari satu akun
     * HR per company. Diekstrak dari `Admin\CompanyController::index()`
     * (2026-09-08, fitur Supervisor) - rantai ini sekarang dipakai ulang di
     * >=3 tempat (dashboard admin lintas-perusahaan, daftar/dashboard
     * Supervisor), jadi satu sumber kebenaran di sini, jangan duplikasi.
     */
    public function hrUserIds(): Collection
    {
        return User::where('company_id', $this->id)->where('role', 'hr')->pluck('id');
    }

    /**
     * ID semua HandwritingSample milik company ini. Tidak ada `company_id`
     * langsung di `Project`/`HandwritingSample` - rantainya
     * company -> user(role=hr) -> Project.created_by -> HandwritingSample,
     * digabung lintas SEMUA akun HR company ini (beda dari
     * `DashboardController::hrDashboard()` yang scope per 1 HR individual
     * lewat `created_by = $user->id`).
     */
    public function sampleIds(): Collection
    {
        return HandwritingSample::whereHas('project', fn ($q) => $q->whereIn('created_by', $this->hrUserIds()))
            ->pluck('id');
    }
}
