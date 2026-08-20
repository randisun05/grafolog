<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "nilai" (titik tunggal terakhir) atau "range" (selisih maks-min hasil ukur)
 * - cuma variable_a yang butuh mode ini, sesuai 20 ambang irregularity user
 * ("Range is more than Nx [variable]"): sisi kiri selalu rentang, sisi kanan
 * (variable_b/compare_value) selalu nilai titik. Lihat ChecklistEngineService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikator_rules', function (Blueprint $table) {
            $table->enum('variable_a_value_mode', ['nilai', 'range'])->default('nilai')->after('variable_a_id');
        });
    }

    public function down(): void
    {
        Schema::table('indikator_rules', function (Blueprint $table) {
            $table->dropColumn('variable_a_value_mode');
        });
    }
};
