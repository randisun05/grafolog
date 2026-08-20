<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('handwriting_samples')->cascadeOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('IDR');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->string('doku_token_id')->nullable();
            $table->string('doku_payment_url')->nullable();
            $table->string('doku_payment_channel')->nullable();
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
        Schema::dropIfExists('payments');
    }
};
