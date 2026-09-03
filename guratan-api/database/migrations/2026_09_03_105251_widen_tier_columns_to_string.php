<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem Products data-driven, Fase 2a (2026-09-03) - lihat
 * guratan-api/CLAUDE.md "Sistem Products data-driven" untuk konteks
 * penuh. Melebarkan 4 kolom `tier` dari enum tertutup jadi string bebas
 * - validasi tier valid mana yang berlaku SEKARANG pindah ke aplikasi
 * (Product::activeCodes(), lihat Fase 2b), bukan lagi dikunci di skema
 * database. Murni perubahan skema, TIDAK ada string baru yang ditulis
 * di fase ini - sama persis 'rapid'/'comprehensive'/'master' yang sudah
 * ada, cuma kolomnya sekarang menerima string apa saja.
 *
 * Driver-aware, pola sama persis
 * 2026_08_03_060730_expand_users_role_enum.php - MySQL (real DB) pakai
 * SQL mentah karena doctrine/dbal (dibutuhkan Blueprint's ->change() di
 * MySQL enum) tidak terpasang; SQLite (test DB) lewat
 * Schema::table()->change() yang ditangani native oleh grammar SQLite-nya
 * Laravel (rebuild tabel) tanpa doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('handwriting_samples', function (Blueprint $table) {
                $table->string('tier', 50)->change();
            });
            Schema::table('personality_reports', function (Blueprint $table) {
                $table->string('tier', 50)->change();
            });
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->string('tier', 50)->change();
            });
            Schema::table('token_costs', function (Blueprint $table) {
                $table->string('tier', 50)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE handwriting_samples MODIFY COLUMN tier VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE personality_reports MODIFY COLUMN tier VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE pricing_plans MODIFY COLUMN tier VARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE token_costs MODIFY COLUMN tier VARCHAR(50) NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('handwriting_samples', function (Blueprint $table) {
                $table->enum('tier', ['rapid', 'comprehensive', 'master'])->change();
            });
            Schema::table('personality_reports', function (Blueprint $table) {
                $table->enum('tier', ['rapid', 'comprehensive', 'master'])->change();
            });
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->enum('tier', ['comprehensive', 'master'])->change();
            });
            Schema::table('token_costs', function (Blueprint $table) {
                $table->enum('tier', ['comprehensive', 'master'])->change();
            });

            return;
        }

        DB::statement("ALTER TABLE handwriting_samples MODIFY COLUMN tier ENUM('rapid', 'comprehensive', 'master') NOT NULL");
        DB::statement("ALTER TABLE personality_reports MODIFY COLUMN tier ENUM('rapid', 'comprehensive', 'master') NOT NULL");
        DB::statement("ALTER TABLE pricing_plans MODIFY COLUMN tier ENUM('comprehensive', 'master') NOT NULL");
        DB::statement("ALTER TABLE token_costs MODIFY COLUMN tier ENUM('comprehensive', 'master') NOT NULL");
    }
};
