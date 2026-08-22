<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot Aspek <-> Topik (many-to-many) - 1 Aspek boleh masuk beberapa
     * topik (mis. "Ambisi" relevan untuk Karier DAN Kepemimpinan). Aspek
     * dipilih sebagai unit tagging (bukan Sindrom yang terlalu luas, atau
     * Indikator yang terlalu granular/704 baris) karena narasi per-level
     * yang sudah dicache ada di level ini.
     */
    public function up(): void
    {
        Schema::create('aspek_topik', function (Blueprint $table) {
            $table->foreignId('aspek_id')->constrained('aspek')->cascadeOnDelete();
            $table->foreignId('topik_id')->constrained('topik')->cascadeOnDelete();
            $table->primary(['aspek_id', 'topik_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspek_topik');
    }
};
