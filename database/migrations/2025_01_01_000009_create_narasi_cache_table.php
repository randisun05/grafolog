<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narasi_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aspek_id')->constrained('aspek')->cascadeOnDelete();
            $table->enum('level', ['very_high', 'high', 'medium', 'low']);
            $table->string('bahasa', 5)->default('id');
            $table->longText('teks_asli');
            $table->longText('teks_hasil');
            $table->string('llm_provider', 30)->nullable();
            $table->timestamps();

            $table->unique(['aspek_id', 'level', 'bahasa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narasi_cache');
    }
};
