<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Opsi jawaban -> satu Indikator KB (null = tidak memengaruhi hasil). */
class SelfCheckOption extends Model
{
    protected $fillable = ['question_id', 'label', 'ilustrasi', 'indikator_id', 'sort'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SelfCheckQuestion::class, 'question_id');
    }

    public function indikator(): BelongsTo
    {
        return $this->belongsTo(Indikator::class);
    }
}
