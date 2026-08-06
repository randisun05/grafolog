<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\TokenPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TokenPurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.doku.client_id' => 'MCH-TEST-0001',
            'services.doku.secret_key' => 'test-secret-key',
            'services.doku.base_url' => 'https://api-sandbox.doku.com',
        ]);

        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
    }

    private function fakeSuccessfulDoku(string $tokenId = 'tok-token-1'): void
    {
        Http::fake([
            'api-sandbox.doku.com/*' => Http::response([
                'message' => ['SUCCESS'],
                'response' => [
                    'order' => ['amount' => '50000', 'invoice_number' => 'whatever'],
                    'payment' => [
                        'token_id' => $tokenId,
                        'url' => 'https://sandbox.doku.com/checkout-link-v2/'.$tokenId,
                        'expired_date' => '20260101000000',
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_non_grafolog_cannot_purchase_tokens(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 10])
            ->assertForbidden();
    }

    public function test_grafolog_can_start_token_purchase(): void
    {
        $this->fakeSuccessfulDoku();
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 10]);

        $response->assertCreated()
            ->assertJsonStructure(['payment_url', 'invoice_number']);

        $this->assertDatabaseHas('token_purchases', [
            'user_id' => $grafolog->id,
            'quantity' => 10,
            'base_amount' => 50000,
            'amount' => 50000,
            'status' => 'pending',
        ]);
    }

    public function test_discount_code_reduces_charged_amount(): void
    {
        $this->fakeSuccessfulDoku();
        $discount = DiscountCode::create(['code' => 'TOKEN10', 'type' => 'percentage', 'value' => 10, 'applicable_tiers' => ['token']]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 10, 'discount_code' => 'token10']);

        $response->assertCreated();
        $this->assertDatabaseHas('token_purchases', [
            'user_id' => $grafolog->id,
            'base_amount' => 50000,
            'amount' => 45000,
            'discount_code_id' => $discount->id,
        ]);
    }

    public function test_invalid_discount_code_rejects_purchase(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 10, 'discount_code' => 'TIDAKADA'])
            ->assertStatus(422);

        $this->assertDatabaseCount('token_purchases', 0);
    }

    public function test_unconfigured_price_rejects_purchase(): void
    {
        TokenPrice::query()->update(['is_active' => false]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 10])
            ->assertStatus(422);
    }

    public function test_unconfigured_doku_returns_clean_503(): void
    {
        config(['services.doku.client_id' => null, 'services.doku.secret_key' => null]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 10]);

        $response->assertStatus(503);
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
    }

    public function test_zero_quantity_rejected(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 0])
            ->assertUnprocessable();
    }
}
