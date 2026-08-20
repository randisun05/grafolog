<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Berapa token yang terpotong dari saldo grafolog setiap laporan tier
     * ini berhasil dibuat (ScoringController::submit). Sama persis pola
     * pricing_plans: histori tersimpan, ganti nilai = nonaktifkan baris
     * lama + buat baris baru. Tabel sengaja KOSONG sampai admin
     * mengonfigurasi tier pertama - kalau kosong,
     * TokenCost::activeTokensFor() mengembalikan null, ScoringController
     * memperlakukannya sebagai 0 token (tidak ada gate) supaya rilis fitur
     * ini TIDAK langsung memblokir grafolog yang saldo awalnya 0.
     */
    public function up(): void
    {
        Schema::create('token_costs', function (Blueprint $table) {
            $table->id();
            $table->enum('tier', ['comprehensive', 'master']);
            $table->unsignedInteger('tokens_required');
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
        Schema::dropIfExists('token_costs');
    }
};
