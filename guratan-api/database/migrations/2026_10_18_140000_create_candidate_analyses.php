<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('input_hash', 64); // sidik jari data masukan - sama = pakai hasil tersimpan
            $table->longText('summary');
            $table->string('source', 20); // ai | aturan
            $table->string('model', 80)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['candidate_profile_id', 'input_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_analyses');
    }
};
