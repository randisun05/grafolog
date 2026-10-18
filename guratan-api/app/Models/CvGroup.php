<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Kelompok variabel CV (mis. Profil, Orang Tua, Pasangan) - diatur admin. */
class CvGroup extends Model
{
    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CvField::class)->orderBy('sort_order')->orderBy('id');
    }
}
