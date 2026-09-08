<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fitur Supervisor Fase 3 (2026-09-08, lihat CLAUDE.md "Peran
     * Supervisor") - percakapan chat interaktif milik SATU Supervisor,
     * TIDAK dibagi antar-Supervisor sekalipun 1 company (keputusan desain
     * eksplisit: ini kerja personal, bukan dokumen resmi perusahaan).
     *
     * `company_id` DISALIN saat percakapan dibuat, bukan live-join ke
     * `users.company_id` - supaya percakapan lama tidak diam-diam pindah
     * konteks kalau company Supervisor itu diganti admin di kemudian hari.
     */
    public function up(): void
    {
        Schema::create('supervisor_chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_chat_conversations');
    }
};
