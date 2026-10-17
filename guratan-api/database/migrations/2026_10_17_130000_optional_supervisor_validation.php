<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase D hasil uji manual (2026-10-17): validasi supervisor jadi OPSIONAL. Laporan baru berstatus
 * `draf`; grafolog menerbitkan sendiri (validation=mandiri) atau meminta validasi supervisor
 * (validation=supervisor) dengan membayar token yang ditransfer ke supervisor peninjau.
 * - token_costs.tier & token_ledger_entries.type jadi string (biaya `validasi_supervisor`,
 *   tipe ledger baru). Driver-aware: doctrine/dbal tidak terpasang.
 * - Laporan lama berada di rezim QA wajib -> di-backfill validation=supervisor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('token_costs', fn (Blueprint $t) => $t->string('tier', 50)->change());
            Schema::table('token_ledger_entries', fn (Blueprint $t) => $t->string('type', 30)->change());
        } else {
            DB::statement('ALTER TABLE token_costs MODIFY COLUMN tier VARCHAR(50) NOT NULL');
            DB::statement('ALTER TABLE token_ledger_entries MODIFY COLUMN type VARCHAR(30) NOT NULL');
        }

        Schema::table('personality_reports', function (Blueprint $table) {
            $table->string('validation', 20)->nullable()->after('review_note'); // mandiri | supervisor
            $table->timestamp('validation_requested_at')->nullable()->after('validation');
            $table->unsignedInteger('validation_fee')->default(0)->after('validation_requested_at');
            $table->foreignId('validation_paid_to')->nullable()->after('validation_fee')->constrained('users')->nullOnDelete();
            $table->timestamp('validation_paid_at')->nullable()->after('validation_paid_to');
        });

        DB::table('personality_reports')->update([
            'validation' => 'supervisor',
            'validation_requested_at' => DB::raw('generated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('personality_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validation_paid_to');
            $table->dropColumn(['validation', 'validation_requested_at', 'validation_fee', 'validation_paid_at']);
        });
        DB::table('token_costs')->whereNotIn('tier', ['comprehensive', 'master'])->delete();
        DB::table('token_ledger_entries')->whereNotIn('type', ['purchase', 'consumption'])->delete();
        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table('token_costs', fn (Blueprint $t) => $t->enum('tier', ['comprehensive', 'master'])->change());
            Schema::table('token_ledger_entries', fn (Blueprint $t) => $t->enum('type', ['purchase', 'consumption'])->change());
        } else {
            DB::statement("ALTER TABLE token_costs MODIFY COLUMN tier ENUM('comprehensive', 'master') NOT NULL");
            DB::statement("ALTER TABLE token_ledger_entries MODIFY COLUMN type ENUM('purchase', 'consumption') NOT NULL");
        }
    }
};
