<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monitoring pengiriman email/WhatsApp - dicatat oleh
     * NotificationDispatcher setiap kali salah satu channel dicoba
     * dikirim (bukan cuma yang berhasil). `status` 'skipped' beda dari
     * 'failed' secara sengaja: skipped = memang belum dicoba (nomor WA
     * kosong / kredensial Fonnte belum diisi), failed = benar-benar
     * dicoba tapi provider menolak/exception - beda akar masalah, beda
     * tindak lanjut yang perlu diambil admin. Read-only sama pola
     * `audit_logs` - lihat `AuditLog` untuk precedent (append-only,
     * tidak pernah diedit/dihapus lewat aplikasi).
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('type', 50);
            $table->string('recipient', 255);
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
