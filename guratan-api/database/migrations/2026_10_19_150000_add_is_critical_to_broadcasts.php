<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Broadcast penting (mis. pemberitahuan insiden data) tetap dikirim email walau pengguna menonaktifkannya. */
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->boolean('is_critical')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn('is_critical');
        });
    }
};
