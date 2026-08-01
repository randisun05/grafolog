<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementVariable extends Model
{
    protected $table = 'measurement_variable';

    protected $fillable = ['kode', 'axis', 'nama'];

    public function kategori(): HasMany
    {
        return $this->hasMany(MeasurementCategory::class, 'variable_id')->orderBy('urutan');
    }

    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }

    public function kategoriUntukNilai(float $nilai): ?MeasurementCategory
    {
        foreach ($this->kategori as $cat) {
            $rentang = str_replace(' ', '', $cat->rentang ?? '');
            if (str_ends_with($rentang, '+')) {
                $min = (float) rtrim($rentang, '+');
                if ($nilai >= $min) return $cat;
                continue;
            }
            $parts = preg_split('/–|-/u', $rentang);
            if (count($parts) === 2 && $nilai >= (float) $parts[0] && $nilai <= (float) $parts[1]) {
                return $cat;
            }
        }
        return null;
    }
}
