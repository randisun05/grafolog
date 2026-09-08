<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur Supervisor Fase 3 (2026-09-08, lihat CLAUDE.md "Peran
     * Supervisor"). Append-only (sama pola AuditLog/NotificationLog - baris
     * tidak pernah di-update setelah dibuat, walau kolom `updated_at` tetap
     * ada lewat `timestamps()` mengikuti precedent kedua model itu, bukan
     * dikustomisasi jadi cuma 1 kolom). `role` (`user`/`assistant`) sengaja
     * literal match field Anthropic API - bukan istilah lain yang perlu
     * diterjemahkan. `token_usage` nullable - fondasi rekap biaya nanti,
     * belum ada konsumen sekarang.
     */
    public function up(): void
    {
        Schema::create('supervisor_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('supervisor_chat_conversations')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->unsignedInteger('token_usage')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_chat_messages');
    }
};
