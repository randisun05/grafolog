<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aspek', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();   // '01'..'40' - kode asli dari file sumber, untuk referensi
            $table->foreignId('sindrom_id')->constrained('sindrom')->cascadeOnDelete();
            $table->string('nama', 150);
            $table->text('keterangan_umum')->nullable();
            $table->longText('narasi_very_high')->nullable();
            $table->longText('narasi_high')->nullable();
            $table->longText('narasi_medium')->nullable();
            $table->longText('narasi_low')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspek');
    }
};
