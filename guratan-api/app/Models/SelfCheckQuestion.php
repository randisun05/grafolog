<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pertanyaan cek tulisan terpandu (lead magnet tier Rapid, 2026-10-17). */
class SelfCheckQuestion extends Model
{
    protected $fillable = ['pertanyaan', 'petunjuk', 'sort', 'is_active'];

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(SelfCheckOption::class, 'question_id')->orderBy('sort');
    }
}
