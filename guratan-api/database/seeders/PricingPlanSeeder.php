<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use Illuminate\Database\Seeder;

/**
 * Harga awal saat pricing_plans masih kosong. TIDAK menimpa harga yang
 * sudah diatur admin lewat panel - hanya bikin baris kalau belum ada
 * baris aktif sama sekali untuk tier itu.
 *
 * PERHATIAN: harga master (149000) masih placeholder yang sama dari
 * config/pricing.php lama, belum pernah dikonfirmasi resmi ke bisnis.
 * Ubah lewat panel admin begitu ada angka resmi, jangan edit di sini.
 */
class PricingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'comprehensive' => 49000,
            'master' => 149000,
        ];

        foreach ($defaults as $tier => $price) {
            if (! PricingPlan::where('tier', $tier)->where('is_active', true)->exists()) {
                PricingPlan::create(['tier' => $tier, 'price' => $price, 'is_active' => true]);
            }
        }
    }
}
