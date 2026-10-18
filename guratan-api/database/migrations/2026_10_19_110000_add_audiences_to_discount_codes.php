<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Saluran aktif kode: client | grafolog | b2b. null = semua saluran. */
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->json('audiences')->nullable()->after('applicable_tiers');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn('audiences');
        });
    }
};
