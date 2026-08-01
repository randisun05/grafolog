<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indikator extends Model
{
    protected $table = 'indikator';

    protected $fillable = ['kode', 'aspek_id', 'nama', 'keterangan'];

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(Aspek::class, 'aspek_id');
    }

    public function referensiKeluar(): HasMany
    {
        return $this->hasMany(IndikatorCrossReference::class, 'indikator_sumber_id');
    }

    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }
}
