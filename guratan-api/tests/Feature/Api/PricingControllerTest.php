<?php

namespace Tests\Feature\Api;

use App\Models\PricingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pricing_endpoint_requires_no_auth(): void
    {
        // Katalog awal (ProductSeeder via migrasi) = 4 produk B2C + 1 paket B2B.
        $this->getJson('/api/pricing')->assertOk()->assertJsonCount(4);
    }

    public function test_only_active_b2c_product_prices_are_returned(): void
    {
        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
        PricingPlan::where('tier', 'master')->update(['is_active' => false]);
        PricingPlan::create(['tier' => 'master', 'price' => 159000, 'is_active' => true]);

        $tiers = collect($this->getJson('/api/pricing')->assertOk()->json())->pluck('price', 'tier');

        $this->assertSame(159000, $tiers['master']);
        $this->assertArrayNotHasKey('comprehensive', $tiers->all()); // tidak dijual lagi
        $this->assertArrayNotHasKey('paket-rekrutmen', $tiers->all()); // khusus B2B
    }
}
