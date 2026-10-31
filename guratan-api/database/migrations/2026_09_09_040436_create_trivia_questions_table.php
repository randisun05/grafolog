<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten Game "Trivia Grafologi" (fitur konten publik Fase 3, 2026-09-09)
 * - lihat guratan-api/CLAUDE.md. `pilihan` JSON array 4 string,
 * `jawaban_benar_index` (0-3) TIDAK PERNAH dikirim ke jalur main publik
 * (Api\Games\TriviaController::questions()) - cuma dipakai server-side
 * saat menilai (check()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trivia_questions', function (Blueprint $table) {
            $table->id();
            $table->text('pertanyaan');
            $table->json('pilihan');
            $table->unsignedTinyInteger('jawaban_benar_index');
            $table->text('penjelasan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trivia_questions');
    }
};
