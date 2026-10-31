<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grafolog_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bank_name', 80);
            $table->text('account_number'); // terenkripsi (cast encrypted)
            $table->string('account_holder', 120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grafolog_bank_accounts');
    }
};
