<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per pasangan (event, user) - `updateOrCreate` di
 * Api\EventRegistrationController::store() menangani daftar-ulang
 * setelah batal (pola sama "reassign update baris yang ada" seperti
 * Assignment - lihat guratan-api/CLAUDE.md HR). Data peserta (nama/
 * email/phone) TIDAK di-snapshot ke kolom sendiri - admin lihat lewat
 * eager-load user, pola sama Admin\PaymentRecapController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['registered', 'cancelled'])->default('registered');
            $table->timestamp('registered_at');
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
