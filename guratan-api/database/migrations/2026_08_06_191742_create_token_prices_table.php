<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Harga per 1 token (Rupiah), dibayar grafolog saat beli token. Pola
     * histori sama persis dengan pricing_plans (Commerce Fase A): ganti
     * harga = nonaktifkan baris lama + buat baris baru, tidak overwrite,
     * supaya invoice pembelian token lama tetap bisa ditelusuri ke harga
     * yang berlaku saat itu. Tabel sengaja dibiarkan KOSONG sampai admin
     * mengisi harga pertama kali lewat panel - lihat TokenPrice::current().
     */
    public function up(): void
    {
        Schema::create('token_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('price_per_token');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_prices');
    }
};
