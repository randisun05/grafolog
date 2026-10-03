<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->string('review_status', 20)->default('menunggu_review')->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
            $table->index('review_status');
        });

        // Laporan lama sudah pernah diterima klien sebelum QA ada - jangan
        // disembunyikan surut.
        DB::table('personality_reports')->update(['review_status' => 'disetujui']);
    }

    public function down(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->dropIndex(['review_status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'reviewed_at', 'review_note']);
        });
    }
};
