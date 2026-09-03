<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Katalog produk awal - TIDAK menimpa edit admin lewat panel (pakai
 * firstOrCreate, bukan updateOrCreate) - hanya bikin baris kalau kode itu
 * belum ada sama sekali. `rapid` sengaja disertakan tapi nonaktif -
 * preservasi histori tier lama (sample/report lama masih ber-tier
 * 'rapid'), BUKAN menghidupkan lagi alur upload rapid yang sudah pensiun
 * 2026-08-01.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['code' => 'comprehensive', 'name' => 'Comprehensive', 'is_active' => true, 'sort_order' => 0],
            ['code' => 'master', 'name' => 'Master', 'is_active' => true, 'sort_order' => 10],
            ['code' => 'rapid', 'name' => 'Rapid (pensiun)', 'is_active' => false, 'sort_order' => 99],
        ];

        foreach ($defaults as $product) {
            Product::firstOrCreate(['code' => $product['code']], $product);
        }
    }
}
