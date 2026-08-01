<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indikator_cross_reference', function (Blueprint $table) {
            $table->id();
            $table->string('indikator_sumber_raw', 255);        // teks asli baris di sheet INDIKATOR
            $table->foreignId('indikator_sumber_id')->nullable()->constrained('indikator')->nullOnDelete();
            $table->string('mereferensikan_ke_kode', 20);        // kode indikator tujuan (TIDAK di-FK, lihat catatan)
            $table->enum('match_status', ['matched', 'unmatched'])->default('unmatched');
            $table->timestamps();

            $table->index('mereferensikan_ke_kode');
            // Catatan: mereferensikan_ke_kode sengaja disimpan sbg kode teks, bukan FK id,
            // karena sebagian baris belum 100% ternormalisasi ke kode indikator yang valid
            // (lihat match_status + README konversi knowledge base).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator_cross_reference');
    }
};
