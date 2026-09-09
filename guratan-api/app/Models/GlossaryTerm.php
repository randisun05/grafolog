<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konten Game "Memory Match Istilah" - lihat guratan-api/CLAUDE.md
 * "Konten publik — Fase 3".
 */
class GlossaryTerm extends Model
{
    protected $fillable = ['istilah', 'definisi', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
