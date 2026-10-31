<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Topik extends Model
{
    protected $table = 'topik';

    protected $fillable = ['nama', 'deskripsi'];

    public function aspek(): BelongsToMany
    {
        return $this->belongsToMany(Aspek::class, 'aspek_topik');
    }

    public function kombinasiTemuan(): BelongsToMany
    {
        return $this->belongsToMany(KombinasiTemuan::class, 'kombinasi_temuan_topik');
    }
}
