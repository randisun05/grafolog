<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dukung input rentang (nilai_min/nilai_max) di samping nilai titik tunggal
 * yang sudah ada - dipakai rule irregularity bertipe "range" (selisih
 * maks-min dibandingkan ambang), lihat App\Services\Scoring\ChecklistEngineService.
 * `nilai` dilonggarkan jadi nullable karena sekarang 1 baris boleh cuma
 * berisi rentang saja (tanpa nilai titik) - lihat MeasurementController::store().
 * Driver-aware sama seperti migrasi enum role sebelumnya (doctrine/dbal
 * tidak terpasang, ->change() MySQL butuh raw statement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurement_readings', function (Blueprint $table) {
            $table->decimal('nilai_min', 10, 4)->nullable()->after('nilai');
            $table->decimal('nilai_max', 10, 4)->nullable()->after('nilai_min');
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('measurement_readings', function (Blueprint $table) {
                $table->decimal('nilai', 10, 4)->nullable()->change();
            });
        } else {
            DB::statement('ALTER TABLE measurement_readings MODIFY COLUMN nilai DECIMAL(10,4) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('measurement_readings', function (Blueprint $table) {
            $table->dropColumn(['nilai_min', 'nilai_max']);
        });
    }
};
