<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dukungan generate narasi terpadu asinkron (queue job, lihat
     * GenerateNarasiTerpaduJob) + guard dedup regenerate - lihat
     * guratan-api/CLAUDE.md "Narasi terpadu - optimalisasi 2026-08-22".
     *
     * `narasi_status` dapat nilai ke-4 `generating` (driver-aware, pola sama
     * dengan enum migrations sebelumnya). `narasi_input_hash` menyimpan
     * fingerprint data yang terakhir dipakai generate - dipakai
     * ReportController::generateNarasiTerpadu() untuk menolak generate ulang
     * yang tidak perlu (skor belum berubah) kecuali `force`. `narasi_
     * generation_error` menyimpan pesan singkat kalau job gagal, supaya
     * frontend bisa menampilkannya alih-alih diam-diam macet di status
     * `generating`.
     */
    public function up(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->string('narasi_input_hash')->nullable()->after('narasi_status');
            $table->string('narasi_generation_error')->nullable()->after('narasi_input_hash');
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('personality_reports', function (Blueprint $table) {
                $table->enum('narasi_status', ['belum_dibuat', 'generating', 'draft', 'final'])
                    ->default('belum_dibuat')->change();
            });

            return;
        }

        DB::statement(
            "ALTER TABLE personality_reports MODIFY COLUMN narasi_status ENUM('belum_dibuat', 'generating', 'draft', 'final') NOT NULL DEFAULT 'belum_dibuat'"
        );
    }

    public function down(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->dropColumn(['narasi_input_hash', 'narasi_generation_error']);
        });

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('personality_reports', function (Blueprint $table) {
                $table->enum('narasi_status', ['belum_dibuat', 'draft', 'final'])
                    ->default('belum_dibuat')->change();
            });

            return;
        }

        DB::statement(
            "ALTER TABLE personality_reports MODIFY COLUMN narasi_status ENUM('belum_dibuat', 'draft', 'final') NOT NULL DEFAULT 'belum_dibuat'"
        );
    }
};
