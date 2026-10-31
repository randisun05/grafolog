<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leaderboard publik untuk 3 mini game (fitur konten publik Fase 3,
 * 2026-09-09) - lihat guratan-api/CLAUDE.md. `nama` ditulis pemain
 * sendiri, TIDAK butuh akun/login - append-only, tidak pernah diedit
 * lewat aplikasi (sama pola AuditLog/NotificationLog, keduanya juga
 * pakai `timestamps()` penuh meski semantiknya append-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_scores', function (Blueprint $table) {
            $table->id();
            $table->enum('game_type', ['tebak_kepribadian', 'trivia', 'memory_match']);
            $table->string('nama', 30);
            $table->unsignedInteger('skor');
            $table->unsignedInteger('durasi_detik')->nullable();
            $table->timestamps();

            $table->index(['game_type', 'skor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_scores');
    }
};
