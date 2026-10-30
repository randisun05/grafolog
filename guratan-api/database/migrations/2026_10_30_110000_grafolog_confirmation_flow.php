<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('grafolog_choice', 12)->nullable();   // pilih | rekomendasi
            $table->string('grafolog_state', 20)->nullable();    // menunggu_pilih | menunggu_konfirmasi | diterima | menunggu_admin
            $table->timestamp('grafolog_deadline')->nullable();  // batas konfirmasi grafolog (24 jam)
            $table->unsignedTinyInteger('grafolog_attempts')->default(0);
        });

        Schema::create('grafolog_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grafolog_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 12)->default('menunggu'); // menunggu | diterima | ditolak | kedaluwarsa | dibatalkan
            $table->string('note', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grafolog_requests');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['grafolog_choice', 'grafolog_state', 'grafolog_deadline', 'grafolog_attempts']));
    }
};
