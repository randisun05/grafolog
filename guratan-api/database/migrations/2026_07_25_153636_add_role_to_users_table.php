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
        // Role sebagai kolom di users (bukan tabel grafolog_profiles terpisah):
        // grafolog di MVP tidak punya atribut tambahan di luar apa yang sudah
        // ada di users, jadi tabel terpisah cuma nambah join tanpa manfaat.
        // Kalau nanti grafolog butuh field khusus (no. lisensi, dst), baru
        // pecah ke tabel profil - jangan diantisipasi sekarang.
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'grafolog'])->default('user')->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
