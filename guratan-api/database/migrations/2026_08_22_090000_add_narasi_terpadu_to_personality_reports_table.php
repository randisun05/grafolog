<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Narasi terpadu: laporan deskriptif mengalir yang dikirim ke klien,
     * dibedakan dari `data` (breakdown Sindrom/Aspek/Indikator) yang sejak
     * 2026-08-22 jadi bahan kerja internal grafolog/admin saja - lihat
     * CLAUDE.md "Narasi terpadu (laporan klien)".
     */
    public function up(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->longText('narasi_terpadu')->nullable()->after('data');
            $table->enum('narasi_bahasa', ['id', 'en'])->nullable()->after('narasi_terpadu');
            $table->enum('narasi_status', ['belum_dibuat', 'draft', 'final'])
                ->default('belum_dibuat')->after('narasi_bahasa');
            $table->string('pdf_path_klien')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->dropColumn(['narasi_terpadu', 'narasi_bahasa', 'narasi_status', 'pdf_path_klien']);
        });
    }
};
