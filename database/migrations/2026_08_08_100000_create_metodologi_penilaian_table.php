<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Label tipe metodologi penilaian (mis. "Master"), supaya knowledge yang
     * cara ukurnya spesifik-metodologi (measurement_variable, dan nanti
     * indikator_rules di KM-E) bisa dikelompokkan begitu metodologi lain
     * muncul di masa depan. Sindrom/Aspek/Indikator TIDAK diberi kolom ini -
     * itu konten psikologis yang sama lintas metodologi apa pun, lihat §3.0
     * "Rencana Knowledge Management System".
     */
    public function up(): void
    {
        Schema::create('metodologi_penilaian', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique(); // 'master' - dipakai kode, bukan tampilan
            $table->string('nama', 150);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('metodologi_penilaian')->insert([
            'kode' => 'master',
            'nama' => 'Master — Penilaian Manual Grafolog Bersertifikat',
            'keterangan' => 'Measurement worksheet fisik diukur manual oleh grafolog bersertifikat, dicocokkan ke Indikator lewat aturan operator.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('metodologi_penilaian');
    }
};
