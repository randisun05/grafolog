<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Mode pelaksanaan yang ditawarkan: subset dari online|offline (diatur admin).
            $table->json('modes')->nullable();
            // Persyaratan yang harus disiapkan klien: [{judul, keterangan}].
            $table->json('requirements')->nullable();
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_mode', 10)->nullable();
            $table->date('scheduled_for')->nullable();   // tanggal pelaksanaan yang diinginkan klien
            $table->date('upload_deadline')->nullable(); // klien berkomitmen mengunggah persyaratan paling lambat tanggal ini
            $table->string('location_note', 255)->nullable(); // kota/tempat untuk mode offline
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['delivery_mode', 'scheduled_for', 'upload_deadline', 'location_note']));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['modes', 'requirements']));
    }
};
