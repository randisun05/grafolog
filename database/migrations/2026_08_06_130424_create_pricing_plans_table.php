<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Commerce inisiatif (2026-08-06): harga pindah dari config/pricing.php
     * (statis, butuh deploy untuk ubah) ke sini. Histori harga lama TIDAK
     * dihapus - baris baru dibuat lalu yang lama di-nonaktifkan
     * (is_active=false), supaya harga di laporan/invoice lama tetap bisa
     * ditelusuri. "Satu baris aktif per tier" ditegakkan di service layer,
     * bukan constraint DB (MySQL tidak punya partial unique index).
     */
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->id();
            $table->enum('tier', ['comprehensive', 'master']);
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tier', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_plans');
    }
};
