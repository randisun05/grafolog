<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ledger tak-berubah (immutable) untuk setiap pergerakan saldo token -
     * satu baris per pembelian sukses (delta positif) dan per laporan
     * berhasil dibuat (delta negatif). users.token_balance adalah cache
     * cepat dari SUM(delta) tabel ini per user, selalu dijaga sinkron oleh
     * TokenWalletService (satu-satunya penulis kolom itu). balance_after
     * disimpan per baris supaya histori bisa direkonstruksi/diaudit tanpa
     * perlu hitung ulang dari baris pertama setiap kali.
     */
    public function up(): void
    {
        Schema::create('token_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['purchase', 'consumption']);
            $table->integer('delta');
            $table->unsignedInteger('balance_after');
            $table->foreignId('report_id')->nullable()->constrained('personality_reports')->nullOnDelete();
            $table->foreignId('token_purchase_id')->nullable()->constrained('token_purchases')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_ledger_entries');
    }
};
