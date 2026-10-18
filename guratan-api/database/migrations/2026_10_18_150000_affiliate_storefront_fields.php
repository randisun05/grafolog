<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Produk mitra tampil sebagai etalase (halaman produk): gambar & keterangan harga dari mitra. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_products', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('ringkasan');
            $table->string('harga', 60)->nullable()->after('image_url'); // teks tampilan dari mitra, mis. "Rp 89.000"
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_products', fn (Blueprint $t) => $t->dropColumn(['image_url', 'harga']));
    }
};
