<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KM-G: state "tercentang" per Indikator per sample - satu-satunya
     * sumber kebenaran untuk hasil checklist. Baris ada = tercentang; tidak
     * ada baris = tidak tercentang. 'sumber' membedakan kenapa tercentang:
     * - auto: cocok lewat indikator_rules (ChecklistEngineService)
     * - cascade: ikut tercentang karena indikator_cross_reference dari
     *   Indikator lain yang tercentang (searah, lihat rencana KM §3.3)
     * - manual: grafolog centang sendiri (termasuk yang tanpa aturan sama
     *   sekali - mayoritas kasus, ~75% Indikator)
     * Auto/cascade hanya mengisi baris yang BELUM ADA SAMA SEKALI - tidak
     * pernah menimpa keputusan grafolog saat re-evaluasi (mis. setelah
     * menambah 1 hasil ukur baru). 'checked' sengaja bukan "baris ada =
     * tercentang, baris tidak ada = tidak" - grafolog meng-uncheck sesuatu
     * harus tetap MENINGGALKAN baris (checked=false) supaya evaluasi ulang
     * tahu ini sudah pernah diputuskan dan tidak mencentangnya lagi.
     */
    public function up(): void
    {
        Schema::create('sample_indikator_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('handwriting_samples')->cascadeOnDelete();
            $table->foreignId('indikator_id')->constrained('indikator')->cascadeOnDelete();
            $table->boolean('checked')->default(true);
            $table->enum('sumber', ['auto', 'cascade', 'manual']);
            $table->foreignId('rule_id')->nullable()->constrained('indikator_rules')->nullOnDelete();
            $table->foreignId('cross_reference_id')->nullable()->constrained('indikator_cross_reference')->nullOnDelete();
            $table->text('keterangan_pemicu')->nullable();
            $table->timestamps();

            $table->unique(['sample_id', 'indikator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_indikator_checks');
    }
};
