<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sebelum ini, staff/company account bisa dibuat tapi tidak pernah bisa
 * dinonaktifkan lewat aplikasi sama sekali - lihat ROADMAP.md "Kesiapan
 * Publikasi" > "gap management". Kolom is_active (bukan hard-delete) -
 * riwayat created_by/audit log/assignment yang mengacu ke user/company itu
 * harus tetap valid setelah dinonaktifkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
