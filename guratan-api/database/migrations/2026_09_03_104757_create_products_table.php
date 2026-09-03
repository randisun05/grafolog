<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog produk/tier data-driven (2026-09-03) - lihat guratan-api/CLAUDE.md
 * "Sistem Products data-driven" untuk konteks penuh kenapa ini dibangun.
 * `code` BUKAN foreign key ke handwriting_samples.tier/personality_reports.tier/
 * pricing_plans.tier/token_costs.tier - kolom-kolom itu tetap string bebas
 * seperti sekarang (cuma dilebarkan dari enum di migrasi terpisah), supaya
 * migrasi ini rendah risiko dan tidak mengunci data historis lewat FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
