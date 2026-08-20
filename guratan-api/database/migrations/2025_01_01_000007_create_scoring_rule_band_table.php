<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_rule_band', function (Blueprint $table) {
            $table->id();
            $table->enum('polaritas', ['HIJAU', 'MERAH']);
            $table->string('label', 30);        // 'Nilai Rendah' | 'Nilai Sedang' | 'Nilai Tinggi'
            $table->string('rentang_skor', 10); // '1-3', '4-6', '7-10', dst
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_rule_band');
    }
};
