<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MGA Fase 06: explicit HR->grafolog assignment, decoupling "who owns
     * the candidate relationship" (HR, via handwriting_samples.created_by)
     * from "who actually scores it" (the assigned grafolog). One row per
     * sample - reassigning updates grafolog_id rather than adding a row.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->unique()->constrained('handwriting_samples')->cascadeOnDelete();
            $table->foreignId('grafolog_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['assigned', 'completed'])->default('assigned');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
