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
        Schema::create('report_aspek_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('personality_reports')->cascadeOnDelete();
            $table->foreignId('aspek_id')->constrained('aspek')->cascadeOnDelete();
            $table->unsignedTinyInteger('skor');
            $table->text('catatan_grafolog')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'aspek_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_aspek_scores');
    }
};
