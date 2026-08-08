<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementVariable extends Model
{
    protected $table = 'measurement_variable';

    protected $fillable = ['kode', 'axis', 'nama', 'metodologi_id'];

    public function kategori(): HasMany
    {
        return $this->hasMany(MeasurementCategory::class, 'variable_id')->orderBy('urutan');
    }

    public function metodologi(): BelongsTo
    {
        return $this->belongsTo(MetodologiPenilaian::class, 'metodologi_id');
    }

    public function indikatorRulesAsA(): HasMany
    {
        return $this->hasMany(IndikatorRule::class, 'variable_a_id');
    }

    public function indikatorRulesAsB(): HasMany
    {
        return $this->hasMany(IndikatorRule::class, 'variable_b_id');
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
                if ($nilai >= $min) {
                    return $cat;
                }

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
