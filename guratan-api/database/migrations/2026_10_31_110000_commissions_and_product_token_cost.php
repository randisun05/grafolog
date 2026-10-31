<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Override biaya token pembuatan/regenerasi laporan untuk produk ini (null = ikut TokenCost per tier).
            $table->unsignedInteger('token_cost')->nullable();
            // Komisi default grafolog per pesanan selesai: percent (0-100 dari nilai bayar bersih) | fixed (Rp).
            $table->string('commission_type', 8)->default('percent');
            $table->unsignedInteger('commission_value')->default(0);
        });

        // Override komisi per produk per grafolog.
        Schema::create('product_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grafolog_id')->constrained('users')->cascadeOnDelete();
            $table->string('commission_type', 8);
            $table->unsignedInteger('commission_value');
            $table->timestamps();
            $table->unique(['product_id', 'grafolog_id']);
        });

        Schema::create('grafolog_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grafolog_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('total');
            $table->string('reference', 120)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('grafolog_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grafolog_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('base_amount');          // nilai bayar bersih pesanan
            $table->string('commission_type', 8);
            $table->unsignedInteger('commission_value');        // aturan yang berlaku saat dihitung (snapshot)
            $table->unsignedBigInteger('amount');               // komisi (Rp)
            $table->string('status', 12)->default('diperoleh'); // diperoleh | dibayar | dibatalkan
            $table->foreignId('payout_id')->nullable()->constrained('grafolog_payouts')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('earned_at');
            $table->timestamps();
            $table->unique(['order_id', 'grafolog_id']);
            $table->index(['grafolog_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grafolog_earnings');
        Schema::dropIfExists('grafolog_payouts');
        Schema::dropIfExists('product_commissions');
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['token_cost', 'commission_type', 'commission_value']));
    }
};
