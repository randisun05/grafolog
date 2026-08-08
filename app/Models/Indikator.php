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

    public function referensiKeluar(): HasMany
    {
        return $this->hasMany(IndikatorCrossReference::class, 'indikator_sumber_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(IndikatorRule::class, 'indikator_id');
    }

    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }
}
