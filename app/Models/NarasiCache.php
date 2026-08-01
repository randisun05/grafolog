<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NarasiCache extends Model
{
    protected $table = 'narasi_cache';

    protected $fillable = [
        'aspek_id', 'level', 'bahasa', 'teks_asli', 'teks_hasil', 'llm_provider',
    ];

    public function aspek(): BelongsTo
    {
        return $this->belongsTo(Aspek::class, 'aspek_id');
    }
}
