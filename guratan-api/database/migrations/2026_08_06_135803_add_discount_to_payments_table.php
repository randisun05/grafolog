<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Commerce Fase D. `amount` keeps meaning "final amount actually
     * charged" (unchanged for old rows - no backfill needed since old
     * payments never had a discount). `base_amount` is nullable because
     * old rows predate this column; new rows always set it.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('base_amount')->nullable()->after('amount');
            $table->foreignId('discount_code_id')->nullable()->after('base_amount')
                ->constrained('discount_codes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn('base_amount');
        });
    }
};
