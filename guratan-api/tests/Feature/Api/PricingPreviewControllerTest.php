<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\PricingPlan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProducts;
use Tests\TestCase;

class PricingPreviewControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProducts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProducts();
        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
        PricingPlan::create(['tier' => 'master', 'price' => 149000, 'is_active' => true]);
    }

    public function test_guest_cannot_preview_pricing(): void
    {
        $this->postJson('/api/pricing/preview', ['tier' => 'comprehensive'])->assertUnauthorized();
    }

    public function test_preview_without_code_returns_base_price(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/pricing/preview', ['tier' => 'comprehensive']);

        $response->assertOk()
            ->assertJsonPath('base_price', 49000)
            ->assertJsonPath('discount_amount', 0)
            ->assertJsonPath('final_price', 49000)
            ->assertJsonPath('code_valid', null);
    }

    public function test_preview_with_valid_percentage_code(): void
    {
        $user = User::factory()->create();
        DiscountCode::create(['code' => 'HEMAT10', 'type' => 'percentage', 'value' => 10]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/pricing/preview', ['tier' => 'comprehensive', 'code' => 'hemat10']);

        $response->assertOk()
            ->assertJsonPath('code_valid', true)
            ->assertJsonPath('discount_amount', 4900)
            ->assertJsonPath('final_price', 44100);
    }

    public function test_preview_with_invalid_code_still_returns_base_price(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/pricing/preview', ['tier' => 'comprehensive', 'code' => 'TIDAKADA']);

        $response->assertOk()
            ->assertJsonPath('code_valid', false)
            ->assertJsonPath('discount_amount', 0)
            ->assertJsonPath('final_price', 49000);
    }

    public function test_preview_with_code_not_applicable_to_tier(): void
    {
        $user = User::factory()->create();
        DiscountCode::create([
            'code' => 'MASTERONLY', 'type' => 'fixed', 'value' => 10000,
            'applicable_tiers' => ['master'],
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/pricing/preview', ['tier' => 'comprehensive', 'code' => 'MASTERONLY']);

        $response->assertOk()->assertJsonPath('code_valid', false)->assertJsonPath('final_price', 49000);
    }

    // Sistem Products data-driven, Fase 2b: bukti tier valid dibaca dari
    // Product::activeCodes(), bukan literal 'in:comprehensive,master' lagi.
    public function test_a_newly_added_active_product_is_accepted_as_tier(): void
    {
        Product::create(['code' => 'deluxe', 'name' => 'Deluxe', 'is_active' => true]);
        PricingPlan::create(['tier' => 'deluxe', 'price' => 199000, 'is_active' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/pricing/preview', ['tier' => 'deluxe']);

        $response->assertOk()->assertJsonPath('base_price', 199000);
    }
}
