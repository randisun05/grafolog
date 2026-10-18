<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-10-18: unggahan tulisan tangan kini PDF hasil scan (bukan foto) - ukuran halaman
 * dibaca dari PDF supaya skala tetap (A4/F4). Akun kandidat B2B memakai password sementara
 * yang dikelola HR (tidak diemail) dan wajib diganti saat login pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sample_images', function (Blueprint $table) {
            $table->unsignedSmallInteger('page_count')->nullable()->after('height');
            $table->unsignedSmallInteger('page_width_mm')->nullable()->after('page_count');
            $table->unsignedSmallInteger('page_height_mm')->nullable()->after('page_width_mm');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('must_change_password'));
        Schema::table('sample_images', fn (Blueprint $t) => $t->dropColumn(['page_count', 'page_width_mm', 'page_height_mm']));
    }
};
