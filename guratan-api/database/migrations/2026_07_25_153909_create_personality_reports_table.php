<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personality_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained('handwriting_samples')->cascadeOnDelete();
            $table->enum('tier', ['rapid', 'comprehensive', 'master']);
            $table->enum('status', ['generating', 'completed', 'failed'])->default('generating');
            $table->json('data')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personality_reports');
    }
};
