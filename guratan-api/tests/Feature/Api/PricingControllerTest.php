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
        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);

        $this->getJson('/api/pricing')->assertOk()->assertJsonCount(1);
    }

    public function test_only_active_prices_are_returned(): void
    {
        PricingPlan::create(['tier' => 'comprehensive', 'price' => 39000, 'is_active' => false]);
        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
        PricingPlan::create(['tier' => 'master', 'price' => 149000, 'is_active' => true]);

        $response = $this->getJson('/api/pricing');

        $response->assertOk()->assertJsonCount(2);
        $this->assertEqualsCanonicalizing(
            [49000, 149000],
            collect($response->json())->pluck('price')->all()
        );
    }
}
