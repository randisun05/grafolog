<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KombinasiSyarat extends Model
{
    protected $table = 'kombinasi_syarat';

    protected $fillable = ['kombinasi_temuan_id', 'level', 'indikator_id', 'aspek_id', 'sindrom_id', 'kondisi'];

    public function kombinasiTemuan(): BelongsTo
    {
        return $this->belongsTo(KombinasiTemuan::class, 'kombinasi_temuan_id');
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(Indikator::class);
    }

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(Aspek::class);
    }

    public function sindrom(): BelongsTo
    {
        return $this->belongsTo(Sindrom::class);
    }
}
