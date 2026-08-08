<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodologiPenilaian extends Model
{
    protected $table = 'metodologi_penilaian';

    protected $fillable = ['kode', 'nama', 'keterangan'];

    public function measurementVariable(): HasMany
    {
        return $this->hasMany(MeasurementVariable::class, 'metodologi_id');
    }

    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }
}
