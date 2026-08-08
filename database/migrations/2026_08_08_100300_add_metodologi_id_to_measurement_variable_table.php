<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Label variabel ukur ke metodologi penilaiannya (lihat §3.0 rencana
     * KM). measurement_category sengaja TIDAK diberi kolom ini juga -
     * kategori selalu milik satu variable_id, jadi metodologinya sudah
     * transitif lewat variable_id -> measurement_variable.metodologi_id;
     * kolom terpisah cuma berisiko drift (kategori "kepunyaan" variabel A
     * tapi berlabel metodologi B).
     */
    public function up(): void
    {
        Schema::table('measurement_variable', function (Blueprint $table) {
            $table->foreignId('metodologi_id')->nullable()->after('id')
                ->constrained('metodologi_penilaian')->nullOnDelete();
        });

        $masterId = DB::table('metodologi_penilaian')->where('kode', 'master')->value('id');
        DB::table('measurement_variable')->update(['metodologi_id' => $masterId]);
    }

    public function down(): void
    {
        Schema::table('measurement_variable', function (Blueprint $table) {
            $table->dropConstrainedForeignId('metodologi_id');
        });
    }
};
