<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftaran grafolog lewat verifikasi data (2026-09-02) - GANTI jalur
 * lama "self-register langsung role=grafolog lewat /auth/register"
 * (lihat RegisterRequest, dipersempit jadi role:user saja di migrasi
 * yang sama ini secara kode, bukan di sini). Sekarang calon grafolog
 * mengisi biodata + unggah bukti profesi (sertifikat/kartu anggota/dsb,
 * bebas format - kolom `catatan` menampung penjelasan bebas), masuk
 * status `pending`, BARU jadi akun `users` sungguhan (role=grafolog,
 * is_active=true) kalau administrator approve lewat
 * Admin\GrafologApplicationController::approve().
 *
 * `password` disimpan HASHED di sini juga (cast 'hashed' di model) -
 * dipakai apa adanya untuk provisioning User saat approve, bukan
 * di-generate ulang - lihat GrafologApplicationController::store().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grafolog_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('phone')->nullable();
            $table->text('catatan')->nullable();
            $table->string('document_path');
            $table->string('document_original_name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grafolog_applications');
    }
};
