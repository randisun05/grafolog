<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan pencairan komisi ke grafolog (transfer dilakukan di luar sistem; ini bukti pembukuan). */
class GrafologPayout extends Model
{
    protected $fillable = ['grafolog_id', 'total', 'reference', 'note', 'paid_by', 'paid_at'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function grafolog(): BelongsTo
    {
        return $this->belongsTo(User::class, 'grafolog_id');
    }
}
