<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-10-18: supervisor digabung ke grafolog (supervisi antar-grafolog).
 * - report_validations: satu baris per penugasan supervisi (pembuat laporan -> grafolog lain),
 *   tenggat hari, token ditahan (escrow) dan baru cair setelah pembuat laporan mengonfirmasi.
 * - grafolog_availabilities.supervision: bersedia menerima supervisi di bulan itu.
 * - order_batches.service_month: bulan layanan yang dipilih HR (kuota grafolog dihitung per bulan).
 * - akun role supervisor lama diubah menjadi grafolog terverifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('personality_reports')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('validator_id')->constrained('users')->cascadeOnDelete();
            // menunggu | disetujui | perlu_revisi (keputusan validator, menunggu konfirmasi pembuat)
            // | selesai (dikonfirmasi, token cair) | ditolak (validator menolak penugasan) | kedaluwarsa
            $table->string('status', 20)->default('menunggu');
            $table->unsignedInteger('fee')->default(0);
            $table->unsignedSmallInteger('deadline_days');
            $table->timestamp('due_at');
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['validator_id', 'status']);
            $table->index(['report_id', 'status']);
        });

        Schema::table('grafolog_availabilities', function (Blueprint $table) {
            $table->boolean('supervision')->default(false)->after('capacity');
        });

        Schema::table('order_batches', function (Blueprint $table) {
            $table->string('service_month', 7)->nullable()->after('grafolog_id');
        });
        DB::table('order_batches')->update(['service_month' => DB::raw('SUBSTR(created_at, 1, 7)')]);

        DB::table('users')->where('role', 'supervisor')->update(['role' => 'grafolog', 'grafolog_status' => 'verified']);
    }

    public function down(): void
    {
        Schema::table('order_batches', fn (Blueprint $t) => $t->dropColumn('service_month'));
        Schema::table('grafolog_availabilities', fn (Blueprint $t) => $t->dropColumn('supervision'));
        Schema::dropIfExists('report_validations');
    }
};
