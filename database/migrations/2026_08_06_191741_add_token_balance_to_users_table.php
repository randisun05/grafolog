<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Token grafolog (2026-08-07): saldo di-cache di sini untuk pengecekan
     * cepat di ScoringController::submit tanpa perlu SUM() token_ledger_entries
     * setiap request. token_ledger_entries tetap jadi sumber kebenaran/audit
     * trail - kolom ini murni cache yang selalu dijaga sinkron lewat
     * TokenWalletService (satu-satunya tempat yang boleh mengubahnya).
     * Default 0 untuk semua role, hanya relevan untuk grafolog.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('token_balance')->default(0)->after('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('token_balance');
        });
    }
};
