<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sindrom tidak punya natural key unik sebelumnya - satu-satunya sebab
     * GrafologiKnowledgeSeeder terpaksa insert buta setiap re-run. 8 baris
     * kode_romawi ('I'..'VIII') sudah pasti unik di data nyata; index ini
     * yang dipakai updateOrCreate di seeder yang dibenahi.
     */
    public function up(): void
    {
        Schema::table('sindrom', function (Blueprint $table) {
            $table->unique('kode_romawi');
        });
    }

    public function down(): void
    {
        Schema::table('sindrom', function (Blueprint $table) {
            $table->dropUnique(['kode_romawi']);
        });
    }
};
