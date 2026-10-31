<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot KombinasiTemuan <-> Topik - temuan kombinasi juga menghasilkan
     * teks interpretasi berdiri sendiri (lihat kombinasi_temuan table),
     * jadi layak ditag topik juga, bukan cuma Aspek.
     */
    public function up(): void
    {
        Schema::create('kombinasi_temuan_topik', function (Blueprint $table) {
            $table->foreignId('kombinasi_temuan_id')->constrained('kombinasi_temuan')->cascadeOnDelete();
            $table->foreignId('topik_id')->constrained('topik')->cascadeOnDelete();
            $table->primary(['kombinasi_temuan_id', 'topik_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kombinasi_temuan_topik');
    }
};
