<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategorisasi topik (mis. Karier, Percintaan) - infrastruktur MURNI
     * untuk tagging, tidak mengubah mekanisme generate laporan sama sekali
     * (ScoringEngineService/NarasiCacheService/ChecklistEngineService/
     * KombinasiTemuanService::evaluate() tetap persis sama). Dibangun 2026-08-22
     * atas permintaan user - "produk turunan" yang bisa dipakai nanti untuk
     * filter laporan per-segmen (mis. B2B minta laporan karier saja), chat
     * interaktif, atau tampilan report biasa - belum diputuskan yang mana,
     * makanya cuma manajemennya yang dibangun, bukan salah satu use case
     * spesifik. Lihat CLAUDE.md "Topik (kategorisasi)".
     */
    public function up(): void
    {
        Schema::create('topik', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topik');
    }
};
