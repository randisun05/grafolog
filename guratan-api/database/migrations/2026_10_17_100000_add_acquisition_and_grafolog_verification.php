<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A (2026-10-17):
 * - users.acquisition_source: dari mana klien datang - website (daftar sendiri, ditangani biro
 *   lewat penugasan), grafolog (dibawa grafolog, referred_by = grafolognya), b2b, admin.
 * - Verifikasi grafolog: grafolog_status pending|verified|rejected + data sertifikat. Grafolog
 *   lama di-backfill verified. Kolom string (bukan enum) supaya tidak butuh migrasi driver-aware.
 * - sample_images.width/height: resolusi tersimpan (minimal FHD dicek saat unggah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('acquisition_source', 20)->nullable()->after('role');
            $table->foreignId('referred_by')->nullable()->after('acquisition_source')
                ->constrained('users')->nullOnDelete();
            $table->string('grafolog_status', 20)->nullable()->after('referred_by');
            $table->string('certificate_number', 100)->nullable()->after('grafolog_status');
            $table->string('certificate_path')->nullable()->after('certificate_number');
            $table->timestamp('grafolog_verified_at')->nullable()->after('certificate_path');
            $table->foreignId('grafolog_verified_by')->nullable()->after('grafolog_verified_at')
                ->constrained('users')->nullOnDelete();
            $table->text('grafolog_review_note')->nullable()->after('grafolog_verified_by');
        });

        DB::table('users')->where('role', 'grafolog')->update(['grafolog_status' => 'verified']);
        DB::table('users')->where('role', 'user')->whereNull('company_id')->update(['acquisition_source' => 'website']);
        DB::table('users')->where('role', 'user')->whereNotNull('company_id')->update(['acquisition_source' => 'b2b']);

        Schema::table('sample_images', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sample_images', function (Blueprint $table) {
            $table->dropColumn(['width', 'height']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropForeign(['grafolog_verified_by']);
            $table->dropColumn([
                'phone', 'acquisition_source', 'referred_by', 'grafolog_status', 'certificate_number',
                'certificate_path', 'grafolog_verified_at', 'grafolog_verified_by', 'grafolog_review_note',
            ]);
        });
    }
};
