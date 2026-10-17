<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * sumber ke-4: 'ciri_bersama' - Indikator ikut tercentang karena Indikator lain
 * dengan ciri tulisan yang sama (nama identik, aspek lain) tercentang. Lihat
 * ChecklistEngineService & App\Support\CiriKey. Driver-aware seperti migrasi
 * enum sebelumnya (doctrine/dbal tidak terpasang).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('sample_indikator_checks', function (Blueprint $table) {
                $table->enum('sumber', ['auto', 'cascade', 'manual', 'ciri_bersama'])->change();
            });
        } else {
            DB::statement("ALTER TABLE sample_indikator_checks MODIFY COLUMN sumber ENUM('auto', 'cascade', 'manual', 'ciri_bersama') NOT NULL");
        }
    }

    public function down(): void
    {
        DB::table('sample_indikator_checks')->where('sumber', 'ciri_bersama')
            ->update(['checked' => false, 'sumber' => 'auto', 'keterangan_pemicu' => null]);

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('sample_indikator_checks', function (Blueprint $table) {
                $table->enum('sumber', ['auto', 'cascade', 'manual'])->change();
            });
        } else {
            DB::statement("ALTER TABLE sample_indikator_checks MODIFY COLUMN sumber ENUM('auto', 'cascade', 'manual') NOT NULL");
        }
    }
};
