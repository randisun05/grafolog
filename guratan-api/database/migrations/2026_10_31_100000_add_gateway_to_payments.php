<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua gateway (DOKU + Xendit): kolom doku_* dinetralkan jadi gateway_*, plus kolom `gateway`
 * (baris lama otomatis 'doku').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('doku_token_id', 'gateway_ref');
            $table->renameColumn('doku_payment_url', 'gateway_url');
            $table->renameColumn('doku_payment_channel', 'gateway_channel');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway', 20)->default('doku')->after('currency');
        });

        Schema::table('token_purchases', function (Blueprint $table) {
            $table->renameColumn('doku_token_id', 'gateway_ref');
            $table->renameColumn('doku_payment_url', 'gateway_url');
        });
        Schema::table('token_purchases', function (Blueprint $table) {
            $table->string('gateway', 20)->default('doku')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('gateway_ref', 'doku_token_id');
            $table->renameColumn('gateway_url', 'doku_payment_url');
            $table->renameColumn('gateway_channel', 'doku_payment_channel');
        });
        Schema::table('token_purchases', function (Blueprint $table) {
            $table->dropColumn('gateway');
        });
        Schema::table('token_purchases', function (Blueprint $table) {
            $table->renameColumn('gateway_ref', 'doku_token_id');
            $table->renameColumn('gateway_url', 'doku_payment_url');
        });
    }
};
