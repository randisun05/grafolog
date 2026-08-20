<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indikator', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();  // kode asli, mis. "01-1'", "35-6-dup3" (lihat catatan perbaikan duplikat)
            $table->foreignId('aspek_id')->constrained('aspek')->cascadeOnDelete();
            $table->string('nama', 255);
            $table->longText('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indikator');
    }
};
