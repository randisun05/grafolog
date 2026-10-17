<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->unique()->constrained('handwriting_samples')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('meeting_url', 500);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('dijadwalkan');
            $table->foreignId('scheduled_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_consultations');
    }
};
