<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit untuk laporan yang dikoreksi skornya atau di-edit
     * narasinya secara manual. Menyimpan snapshot `personality_reports.data`
     * SEBELUM perubahan diterapkan - PersonalityReport.data sendiri selalu
     * mencerminkan versi TERKINI (tidak ada perubahan di ReportController::
     * show/pdf yang sudah ada), tabel ini murni riwayat.
     */
    public function up(): void
    {
        Schema::create('report_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('personality_reports')->cascadeOnDelete();
            $table->enum('jenis', ['koreksi_skor', 'edit_manual']);
            $table->json('data');
            $table->text('catatan')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_revisions');
    }
};
