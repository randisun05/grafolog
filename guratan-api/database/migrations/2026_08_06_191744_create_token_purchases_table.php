<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pembelian token oleh grafolog lewat DOKU - sengaja tabel terpisah
     * dari `payments` (bukan menambah kolom nullable ke sana) karena
     * `payments.sample_id` NOT NULL dan sudah punya makna spesifik
     * "pembayaran untuk satu sample klien"; token purchase tidak terikat
     * sample sama sekali. Struktur kolom dibuat semirip mungkin dengan
     * `payments` (invoice_number, status, doku_*, notification_payload)
     * supaya DokuService & webhook /api/payments/notification bisa
     * menangani keduanya tanpa cabang logika yang aneh.
     */
    public function up(): void
    {
        Schema::create('token_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('base_amount');
            $table->foreignId('discount_code_id')->nullable()->constrained('discount_codes')->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->string('invoice_number', 64)->unique();
            $table->string('doku_token_id')->nullable();
            $table->string('doku_payment_url')->nullable();
            $table->json('notification_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_purchases');
    }
};
