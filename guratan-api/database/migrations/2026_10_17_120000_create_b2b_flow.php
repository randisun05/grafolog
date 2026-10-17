<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase C hasil uji manual (2026-10-17): B2B - pendaftaran & verifikasi perusahaan, profil
 * (grafolog/klien/kandidat), posisi lowongan, kandidat, pesanan B2B per batch (HR memilih
 * grafolog), analisis kandidat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Perusahaan lama dibuat admin -> langsung terverifikasi.
            $table->string('status', 20)->default('verified')->after('name');
            $table->string('industry', 100)->nullable()->after('status');
            $table->string('employee_range', 20)->nullable()->after('industry');
            $table->string('phone', 30)->nullable()->after('employee_range');
            $table->string('address', 500)->nullable()->after('phone');
            $table->string('website')->nullable()->after('address');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Profil grafolog (dipakai HR memilih grafolog) dan profil klien.
            $table->string('city', 100)->nullable()->after('phone');
            $table->text('bio')->nullable()->after('city');
            $table->json('specializations')->nullable()->after('bio');
            $table->unsignedTinyInteger('experience_years')->nullable()->after('specializations');
            $table->date('birth_date')->nullable()->after('experience_years');
            $table->string('gender', 10)->nullable()->after('birth_date');
            $table->string('education', 50)->nullable()->after('gender');
            $table->string('occupation', 100)->nullable()->after('education');
        });

        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('key_aspek_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('education', 50)->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->text('notes')->nullable();
            $table->string('stage', 20)->default('baru'); // baru | asesmen | selesai | lanjut | tidak_lanjut
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
        });

        Schema::create('order_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('grafolog_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('menunggu_pembayaran');
            $table->unsignedInteger('base_amount');
            $table->unsignedInteger('amount');
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->after('id')->constrained('order_batches')->nullOnDelete();
            $table->foreignId('grafolog_id')->nullable()->after('sample_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_batch_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropConstrainedForeignId('order_batch_id'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grafolog_id');
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('order_batches');
        Schema::dropIfExists('candidate_profiles');
        Schema::dropIfExists('job_positions');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['city', 'bio', 'specializations', 'experience_years', 'birth_date', 'gender', 'education', 'occupation']);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['status', 'industry', 'employee_range', 'phone', 'address', 'website', 'verified_at', 'review_note']);
        });
    }
};
