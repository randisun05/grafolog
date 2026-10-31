<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1 baris syarat = 1 kondisi di 1 level (Indikator/Aspek/Sindrom).
     * Semua syarat 1 kombinasi_temuan digabung AND/OR sesuai
     * kombinasi_temuan.logika_gabung. Kolom FK per-level dibuat eksplisit
     * (indikator_id/aspek_id/sindrom_id, cuma 1 yang terisi sesuai `level`)
     * bukan polymorphic generic - pola yang sama dengan indikator_rules
     * (variable_a_id/variable_b_id/depends_on_indikator_id eksplisit),
     * supaya FK constraint & eager-load tetap jalan normal per relasi.
     *
     * `kondisi`: untuk level=indikator cuma 'tercentang'/'tidak_tercentang'
     * (Indikator itu boolean, tidak punya "level tinggi/rendah" sendiri -
     * itu konsep Aspek/Sindrom). Untuk level=aspek/sindrom, salah satu dari
     * 4 bucket yang sudah dipakai ScoringEngineService::narasiLevelUntukSkor()
     * (low/medium/high/very_high) - dipilih supaya konsisten dengan sistem
     * level yang sudah ada, bukan skema baru.
     */
    public function up(): void
    {
        Schema::create('kombinasi_syarat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kombinasi_temuan_id')->constrained('kombinasi_temuan')->cascadeOnDelete();
            $table->enum('level', ['indikator', 'aspek', 'sindrom']);
            $table->foreignId('indikator_id')->nullable()->constrained('indikator')->cascadeOnDelete();
            $table->foreignId('aspek_id')->nullable()->constrained('aspek')->cascadeOnDelete();
            $table->foreignId('sindrom_id')->nullable()->constrained('sindrom')->cascadeOnDelete();
            $table->string('kondisi');
            $table->timestamps();

            $table->index('kombinasi_temuan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kombinasi_syarat');
    }
};
