<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\TokenCost;
use App\Models\TokenLedgerEntry;
use App\Models\TokenPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_returns_null_when_unconfigured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/tokens/price')
            ->assertOk()->assertJsonPath('price_per_token', null);
    }

    public function test_price_returns_active_value(): void
    {
        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/tokens/price')
            ->assertOk()->assertJsonPath('price_per_token', 5000);
    }

    public function test_wallet_requires_grafolog_role(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user, 'sanctum')->getJson('/api/tokens/wallet')->assertForbidden();
    }

    public function test_wallet_returns_balance_price_and_recent_transactions(): void
    {
        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 7]);
        TokenLedgerEntry::create([
            'user_id' => $grafolog->id, 'type' => 'purchase', 'delta' => 10, 'balance_after' => 10,
        ]);
        TokenLedgerEntry::create([
            'user_id' => $grafolog->id, 'type' => 'consumption', 'delta' => -3, 'balance_after' => 7,
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')->getJson('/api/tokens/wallet');

        $response->assertOk()
            ->assertJsonPath('balance', 7)
            ->assertJsonPath('price_per_token', 5000)
            ->assertJsonPath('costs.comprehensive', null)
            ->assertJsonPath('costs.master', null)
            ->assertJsonCount(2, 'transactions');
    }

    public function test_wallet_includes_active_token_cost_per_tier(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        TokenCost::setTokensFor('comprehensive', 5, $admin);
        TokenCost::setTokensFor('master', 10, $admin);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/tokens/wallet')
            ->assertOk()
            ->assertJsonPath('costs.comprehensive', 5)
            ->assertJsonPath('costs.master', 10);
    }

    public function test_preview_computes_final_amount_with_valid_discount(): void
    {
        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
        DiscountCode::create(['code' => 'TOKEN10', 'type' => 'percentage', 'value' => 10, 'applicable_tiers' => ['token']]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/preview', ['quantity' => 10, 'code' => 'token10']);

        $response->assertOk()
            ->assertJsonPath('base_amount', 50000)
            ->assertJsonPath('code_valid', true)
            ->assertJsonPath('discount_amount', 5000)
            ->assertJsonPath('final_amount', 45000);
    }

    public function test_preview_reports_invalid_code_without_error(): void
    {
        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/preview', ['quantity' => 4, 'code' => 'TIDAKADA']);

        $response->assertOk()
            ->assertJsonPath('code_valid', false)
            ->assertJsonPath('final_amount', 20000);
    }

    public function test_preview_fails_when_price_unconfigured(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/preview', ['quantity' => 4])
            ->assertStatus(422);
    }

    public function test_discount_code_scoped_to_master_tier_does_not_apply_to_token(): void
    {
        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
        DiscountCode::create(['code' => 'MASTERONLY', 'type' => 'fixed', 'value' => 1000, 'applicable_tiers' => ['master']]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/preview', ['quantity' => 4, 'code' => 'masteronly']);

        $response->assertOk()->assertJsonPath('code_valid', false);
    }
}
