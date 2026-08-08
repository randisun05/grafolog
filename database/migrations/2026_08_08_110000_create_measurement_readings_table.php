<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KM-G: hasil ukur fisik grafolog untuk 1 sample (measurement worksheet),
     * per variabel ukur (measurement_variable). Ini input mentah yang nanti
     * dievaluasi App\Services\Scoring\ChecklistEngineService lewat
     * indikator_rules untuk auto-centang Indikator - lihat CLAUDE.md.
     */
    public function up(): void
    {
        Schema::create('measurement_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('handwriting_samples')->cascadeOnDelete();
            $table->foreignId('variable_id')->constrained('measurement_variable')->cascadeOnDelete();
            $table->decimal('nilai', 10, 4);
            $table->timestamps();

            $table->unique(['sample_id', 'variable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurement_readings');
    }
};
