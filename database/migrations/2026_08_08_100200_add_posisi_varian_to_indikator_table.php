<?php

use App\Support\IndikatorKode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Posisi 1-10" dan "varian huruf a/b/c" tadinya cuma tersirat lewat
     * parsing string kode (mis. "01-3a") - bentuk hardcode yang ingin
     * dihindari (lihat §3.1 "Rencana Knowledge Management System"). Jadikan
     * field asli, backfill sekali dari 704 baris kode yang sudah ada.
     * Nullable di level DB (pola yang sama seperti `handwriting_samples.
     * project_id`) - form Indikator di KM-D yang mewajibkan pengisian.
     */
    public function up(): void
    {
        Schema::table('indikator', function (Blueprint $table) {
            $table->unsignedTinyInteger('posisi')->nullable()->after('kode');
            $table->string('varian', 5)->nullable()->after('posisi');
        });

        foreach (DB::table('indikator')->select('id', 'kode')->cursor() as $row) {
            $parsed = IndikatorKode::parse($row->kode);
            DB::table('indikator')->where('id', $row->id)->update([
                'posisi' => $parsed['posisi'],
                'varian' => $parsed['varian'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('indikator', function (Blueprint $table) {
            $table->dropColumn(['posisi', 'varian']);
        });
    }
};
