<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sindrom extends Model
{
    protected $table = 'sindrom';

    protected $fillable = [
        'kode_romawi', 'nama', 'polaritas_inferred', 'catatan_polaritas',
    ];

    public function aspek(): HasMany
    {
        return $this->hasMany(Aspek::class, 'sindrom_id');
    }

    public function isHijau(): bool
    {
        return $this->polaritas_inferred === 'HIJAU';
    }
}
