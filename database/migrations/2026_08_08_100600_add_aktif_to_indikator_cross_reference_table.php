<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KM-F (2026-08-08): mengaktifkan `indikator_cross_reference` sebagai
     * data yang bisa dikelola admin (lihat §3.3 rencana KM). Kolom ini yang
     * jadi status "hidup" 1 baris referensi silang - jika admin nonaktifkan
     * 1 baris, `GrafologiKnowledgeSeeder` (dibenahi bareng migrasi ini)
     * WAJIB tidak menimpanya balik ke true saat reseed.
     */
    public function up(): void
    {
        Schema::table('indikator_cross_reference', function (Blueprint $table) {
            $table->boolean('aktif')->default(true)->after('match_status');
        });
    }

    public function down(): void
    {
        Schema::table('indikator_cross_reference', function (Blueprint $table) {
            $table->dropColumn('aktif');
        });
    }
};
