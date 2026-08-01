<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aspek extends Model
{
    protected $table = 'aspek';

    protected $fillable = [
        'kode', 'sindrom_id', 'nama', 'keterangan_umum',
        'narasi_very_high', 'narasi_high', 'narasi_medium', 'narasi_low',
    ];

    public function sindrom(): BelongsTo
    {
        return $this->belongsTo(Sindrom::class, 'sindrom_id');
    }

    public function indikator(): HasMany
    {
        return $this->hasMany(Indikator::class, 'aspek_id');
    }

    /** Cari aspek berdasarkan kode asli dari file sumber (mis. '01'), bukan id database. */
    public static function findByKode(string $kode): ?self
    {
        return static::where('kode', $kode)->first();
    }

    public function getNarasiAttribute(): array
    {
        return [
            'very_high' => $this->narasi_very_high,
            'high'      => $this->narasi_high,
            'medium'    => $this->narasi_medium,
            'low'       => $this->narasi_low,
        ];
    }
}
