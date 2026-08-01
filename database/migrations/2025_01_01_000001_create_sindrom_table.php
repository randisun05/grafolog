<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sindrom', function (Blueprint $table) {
            $table->id();
            $table->string('kode_romawi', 5);      // 'I'..'VIII' - referensi ke dokumen sumber
            $table->string('nama', 100);
            $table->enum('polaritas_inferred', ['HIJAU', 'MERAH']);
            $table->text('catatan_polaritas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sindrom');
    }
};
