<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Commerce Fase B. applicable_tiers null = berlaku semua tier.
     * max_uses null = tanpa batas pemakaian. Keputusan bisnis (2026-08-06):
     * dua tipe diskon dibutuhkan (persentase + potongan tetap), bukan cuma
     * satu.
     */
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->enum('type', ['percentage', 'fixed']);
            $table->unsignedInteger('value');
            $table->json('applicable_tiers')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
