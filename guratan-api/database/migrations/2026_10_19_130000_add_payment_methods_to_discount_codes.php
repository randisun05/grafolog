<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Grup metode bayar yang diizinkan untuk kode ini (config/payment_methods.php). null = semua. */
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->json('payment_methods')->nullable()->after('audiences');
        });
    }

    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropColumn('payment_methods');
        });
    }
};
