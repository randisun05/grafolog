<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sindrom', function (Blueprint $table) {
            $table->string('nama_id')->nullable()->after('nama');
        });

        Schema::table('aspek', function (Blueprint $table) {
            $table->string('nama_id')->nullable()->after('nama');
            $table->text('keterangan_umum_id')->nullable()->after('keterangan_umum');
            $table->text('narasi_very_high_id')->nullable()->after('narasi_low');
            $table->text('narasi_high_id')->nullable()->after('narasi_very_high_id');
            $table->text('narasi_medium_id')->nullable()->after('narasi_high_id');
            $table->text('narasi_low_id')->nullable()->after('narasi_medium_id');
        });

        // Dengan LLM_PROVIDER=none, NarasiCacheService dulu menyimpan teks
        // Inggris apa adanya berlabel bahasa='id'. Bukan terjemahan - buang.
        DB::table('narasi_cache')->where('bahasa', 'id')->whereColumn('teks_hasil', 'teks_asli')->delete();
    }

    public function down(): void
    {
        Schema::table('sindrom', fn (Blueprint $table) => $table->dropColumn('nama_id'));
        Schema::table('aspek', fn (Blueprint $table) => $table->dropColumn([
            'nama_id', 'keterangan_umum_id', 'narasi_very_high_id', 'narasi_high_id', 'narasi_medium_id', 'narasi_low_id',
        ]));
    }
};
