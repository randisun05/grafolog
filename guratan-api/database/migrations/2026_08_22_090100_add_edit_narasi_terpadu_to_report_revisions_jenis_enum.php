<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 3rd `jenis` value for the narasi terpadu edit/finalize flow
     * (ReportController::updateNarasiTerpadu) - same driver-aware pattern as
     * 2026_08_03_060730_expand_users_role_enum.php: raw ALTER for real
     * MySQL (doctrine/dbal not installed), Schema::table()->enum()->change()
     * for sqlite (native table rebuild, no doctrine/dbal needed there).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('report_revisions', function (Blueprint $table) {
                $table->enum('jenis', ['koreksi_skor', 'edit_manual', 'edit_narasi_terpadu'])->change();
            });

            return;
        }

        DB::statement(
            "ALTER TABLE report_revisions MODIFY COLUMN jenis ENUM('koreksi_skor', 'edit_manual', 'edit_narasi_terpadu') NOT NULL"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('report_revisions', function (Blueprint $table) {
                $table->enum('jenis', ['koreksi_skor', 'edit_manual'])->change();
            });

            return;
        }

        DB::statement(
            "ALTER TABLE report_revisions MODIFY COLUMN jenis ENUM('koreksi_skor', 'edit_manual') NOT NULL"
        );
    }
};
