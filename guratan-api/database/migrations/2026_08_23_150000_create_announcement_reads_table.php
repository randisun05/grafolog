<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebelum ini "dibaca/dismiss" cuma state frontend lokal (`dismissedIds`
 * di DashboardView.vue) - tidak persisten, banner yang sama muncul lagi
 * tiap kunjungan baru. User eksplisit minta notifikasi yang genuinely
 * personal & persisten (mekanisme bell/inbox) - lihat ROADMAP.md
 * "Notifikasi/Pengumuman/Promo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
