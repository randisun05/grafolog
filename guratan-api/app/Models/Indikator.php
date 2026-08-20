<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indikator extends Model
{
    protected $table = 'indikator';

    protected $fillable = ['kode', 'posisi', 'varian', 'rule_group_logic', 'aspek_id', 'nama', 'keterangan'];

    protected $attributes = [
        'rule_group_logic' => 'OR',
    ];

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(Aspek::class, 'aspek_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(IndikatorRule::class, 'indikator_id');
    }

    /**
     * Aturan indikator_checked milik Indikator LAIN yang bergantung pada
     * status tercentang Indikator ini - dulu "referensi silang keluar"
     * lewat tabel terpisah, sekarang cuma query balik ke indikator_rules
     * (2026-08-19, lihat ChecklistEngineService).
     */
    public function dependentRules(): HasMany
    {
        return $this->hasMany(IndikatorRule::class, 'depends_on_indikator_id');
    }

    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }
}
