<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoringRuleBand extends Model
{
    protected $table = 'scoring_rule_band';

    protected $fillable = ['polaritas', 'label', 'rentang_skor'];

    /**
     * Cari label band (Rendah/Sedang/Tinggi) untuk sebuah skor 1-10,
     * sesuai polaritas sindrom (HIJAU/MERAH). Port dari bandForScore() di JS.
     */
    public static function labelUntukSkor(int $skor, string $polaritas): string
    {
        $bands = static::where('polaritas', $polaritas)->get();
        foreach ($bands as $band) {
            $parts = explode('-', $band->rentang_skor);
            $lo = (int) $parts[0];
            $hi = isset($parts[1]) ? (int) $parts[1] : $lo;
            if ($skor >= $lo && $skor <= $hi) {
                return $band->label;
            }
        }

        return $bands->last()->label ?? 'Tidak diketahui';
    }
}
