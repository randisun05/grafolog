<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konten Game "Memory Match Istilah" (fitur konten publik Fase 3,
 * 2026-09-09) - lihat guratan-api/CLAUDE.md. Sederhana (istilah+definisi)
 * sengaja bukan diambil dari `measurement_variable`/`aspek` langsung -
 * istilah di sini dikurasi khusus untuk konteks kuis kartu ringkas,
 * bukan definisi teknis penuh yang dipakai mesin scoring.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glossary_terms', function (Blueprint $table) {
            $table->id();
            $table->string('istilah');
            $table->text('definisi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glossary_terms');
    }
};
