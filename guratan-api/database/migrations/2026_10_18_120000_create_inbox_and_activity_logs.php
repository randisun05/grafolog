<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-10-18: kotak masuk notifikasi (+ broadcast admin) dan log aktivitas ringan.
 * - inbox_messages: notifikasi per user (juga dikirim email bila diminta).
 * - broadcasts: pesan massal admin ke role tertentu.
 * - activity_logs: jejak request tulis & login (tanpa body/payload), dipangkas berkala.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('target_roles')->nullable(); // null = semua pengguna
            $table->boolean('send_email')->default(false);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50);
            $table->string('title');
            $table->text('body');
            $table->string('url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 20); // login | login_gagal | logout | request
            $table->string('method', 8)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('route', 120)->nullable();
            $table->unsignedSmallInteger('status')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 160)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('broadcasts');
    }
};
