<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deskriptif_lookup', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();  // '1','2','3' dari sumber
            $table->text('keterangan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deskriptif_lookup');
    }
};
