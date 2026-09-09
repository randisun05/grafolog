<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan publik dengan pendaftaran DI DALAM Guratan (fitur konten
 * publik Fase 2, 2026-09-09) - lihat guratan-api/CLAUDE.md. Tidak ada
 * status "selesai" di sini - dihitung client-side dari starts_at/ends_at
 * vs waktu sekarang, sama filosofi badge "Kadaluarsa" computed
 * client-side di Kontrak B2B (lihat guratan-web/CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('description');
            $table->string('cover_image_path')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
