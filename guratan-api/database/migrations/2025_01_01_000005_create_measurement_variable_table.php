<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_variable', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();  // '1', '15 & 16', '19 & 20 (d-stem)', dst - sesuai sumber
            $table->enum('axis', ['vertical', 'horizontal']);
            $table->string('nama', 150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_variable');
    }
};
