<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable di skema (akun lama tidak punya nomor sama sekali, tidak
     * bisa diisi retroaktif) - wajib diisi cuma di lapisan validasi Form
     * Request untuk pendaftaran BARU (lihat RegisterRequest dkk). Dipakai
     * untuk kirim notifikasi WhatsApp paralel dengan email (lihat
     * WhatsAppService) - user lama yang belum punya nomor otomatis
     * dilewati (WA dilewati, email tetap terkirim seperti biasa).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
