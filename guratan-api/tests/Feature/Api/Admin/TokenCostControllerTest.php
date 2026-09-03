<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Product;
use App\Models\TokenCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProducts;
use Tests\TestCase;

class TokenCostControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProducts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProducts();
    }

    public function test_guest_cannot_view_or_update_token_costs(): void
    {
        $this->getJson('/api/admin/token-costs')->assertUnauthorized();
        $this->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => 1])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => 1])
            ->assertForbidden();
    }

    public function test_administrator_can_set_cost_and_old_row_is_deactivated(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $old = TokenCost::create(['tier' => 'comprehensive', 'tokens_required' => 1, 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => 2]);

        $response->assertCreated()
            ->assertJsonPath('tier', 'comprehensive')
            ->assertJsonPath('tokens_required', 2)
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('token_costs', ['id' => $old->id, 'is_active' => false]);
        $this->assertSame(2, TokenCost::activeTokensFor('comprehensive'));
    }

    public function test_cost_change_is_audit_logged(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-costs/master', ['tokens_required' => 3])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'ubah_biaya_token',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_invalid_tier_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-costs/rapid', ['tokens_required' => 1])
            ->assertNotFound();
    }

    public function test_negative_tokens_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => -1])
            ->assertUnprocessable();
    }

    public function test_zero_tokens_allowed(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => 0])
            ->assertCreated();
    }

    public function test_index_lists_cost_history(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        TokenCost::setTokensFor('comprehensive', 1, $admin);
        TokenCost::setTokensFor('comprehensive', 2, $admin);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/token-costs');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    // Sistem Products data-driven, Fase 2b: bukti tier valid dibaca dari
    // Product::activeCodes(), bukan literal ['comprehensive','master'] lagi.
    public function test_a_newly_added_active_product_is_accepted_as_tier(): void
    {
        Product::create(['code' => 'deluxe', 'name' => 'Deluxe', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-costs/deluxe', ['tokens_required' => 3]);

        $response->assertCreated()->assertJsonPath('tier', 'deluxe')->assertJsonPath('tokens_required', 3);
    }
}
