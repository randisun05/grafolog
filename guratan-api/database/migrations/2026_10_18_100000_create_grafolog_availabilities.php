<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal ketersediaan grafolog per bulan (2026-10-18): HR hanya bisa memilih grafolog yang
 * tersedia di bulan pemesanan. Tanpa baris untuk bulan itu = tersedia (tanpa batas kuota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grafolog_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7); // YYYY-MM
            $table->string('status', 20)->default('tersedia'); // tersedia | terbatas | penuh | libur
            $table->unsignedSmallInteger('capacity')->nullable(); // maks. pekerjaan aktif; null = tanpa batas
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grafolog_availabilities');
    }
};
