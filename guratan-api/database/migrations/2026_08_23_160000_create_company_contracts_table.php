<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B Fase 3 (ROADMAP.md "Kesiapan Publikasi") - kontrak custom
 * sales-led per perusahaan, keputusan eksplisit user lewat
 * AskUserQuestion: sistem CUMA mencatat kesepakatan, TIDAK menghitung
 * tagihan otomatis. nilai_kontrak murni referensi internal admin, tidak
 * dipakai kalkulasi/gate pembayaran apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('judul');
            $table->text('catatan')->nullable();
            $table->decimal('nilai_kontrak', 15, 2)->nullable();
            $table->date('mulai_at');
            $table->date('berakhir_at')->nullable();
            $table->enum('status', ['draft', 'aktif', 'dihentikan'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contracts');
    }
};
