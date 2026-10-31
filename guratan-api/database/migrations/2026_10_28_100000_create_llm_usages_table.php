<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Log pemakaian & biaya AI - metadata saja, TIDAK menyimpan isi prompt/jawaban (privasi). */
    public function up(): void
    {
        Schema::create('llm_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('model', 80)->nullable();
            $table->string('purpose', 40);
            $table->string('status', 10); // ok | gagal | cache
            $table->unsignedTinyInteger('key_slot')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 120)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usages');
    }
};
