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
            'services.xendit.secret_key' => 'xnd_development_test-secret-key',
            'services.xendit.callback_token' => 'test-callback-token',
            'services.xendit.base_url' => 'https://api.xendit.co',
        ]);

        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
    }

    private function fakeSuccessfulXendit(string $invoiceId = 'inv-token-1'): void
    {
        Http::fake([
            'api.xendit.co/v2/invoices' => Http::response([
                'id' => $invoiceId,
                'external_id' => 'whatever',
                'status' => 'PENDING',
                'invoice_url' => 'https://checkout.xendit.co/web/'.$invoiceId,
                'expiry_date' => '2026-01-01T00:00:00.000Z',
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
        $this->fakeSuccessfulXendit();
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
        $this->fakeSuccessfulXendit();
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

    public function test_unconfigured_xendit_returns_clean_503(): void
    {
        config(['services.xendit.secret_key' => null]);
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
