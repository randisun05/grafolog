<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 3rd rule_type 'indikator_checked' - unifikasi cross-reference (dulu
 * mekanisme cascade terpisah, tabel indikator_cross_reference) jadi bagian
 * dari sistem aturan yang sama (AND/OR lewat rule_group_logic, sama seperti
 * category/comparison). variable_a_id dilonggarkan nullable karena rule
 * tipe ini tidak pakai measurement variable sama sekali - sisi kirinya
 * adalah status tercentang Indikator lain (depends_on_indikator_id).
 * Driver-aware (raw ALTER utk MySQL, ->change() native utk sqlite test DB)
 * sama seperti migrasi enum role sebelumnya - doctrine/dbal tidak terpasang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikator_rules', function (Blueprint $table) {
            $table->foreignId('depends_on_indikator_id')->nullable()->after('indikator_id')
                ->constrained('indikator')->cascadeOnDelete();
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('indikator_rules', function (Blueprint $table) {
                $table->enum('rule_type', ['category', 'comparison', 'indikator_checked'])->change();
                $table->foreignId('variable_a_id')->nullable()->change();
            });
        } else {
            DB::statement("ALTER TABLE indikator_rules MODIFY COLUMN rule_type ENUM('category', 'comparison', 'indikator_checked') NOT NULL");
            DB::statement('ALTER TABLE indikator_rules MODIFY COLUMN variable_a_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::table('indikator_rules', function (Blueprint $table) {
            $table->dropForeign(['depends_on_indikator_id']);
            $table->dropColumn('depends_on_indikator_id');
        });
    }
};
