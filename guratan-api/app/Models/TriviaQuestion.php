<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konten Game "Trivia Grafologi" - lihat guratan-api/CLAUDE.md "Konten
 * publik — Fase 3". `jawaban_benar_index` (0-3) TIDAK PERNAH dikirim ke
 * jalur main publik (Api\Games\TriviaController::questions()).
 */
class TriviaQuestion extends Model
{
    protected $fillable = ['pertanyaan', 'pilihan', 'jawaban_benar_index', 'penjelasan', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'pilihan' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
