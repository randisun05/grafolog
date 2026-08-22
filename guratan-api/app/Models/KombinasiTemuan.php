<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KombinasiTemuan extends Model
{
    protected $table = 'kombinasi_temuan';

    protected $fillable = ['nama', 'teks_interpretasi', 'logika_gabung'];

    protected $attributes = [
        'logika_gabung' => 'OR',
    ];

    public function syarat(): HasMany
    {
        return $this->hasMany(KombinasiSyarat::class, 'kombinasi_temuan_id');
    }

    public function topik(): BelongsToMany
    {
        return $this->belongsToMany(Topik::class, 'kombinasi_temuan_topik');
    }
}
