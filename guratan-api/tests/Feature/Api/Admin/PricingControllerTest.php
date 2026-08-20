<?php

namespace Tests\Feature\Api\Admin;

use App\Models\PricingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_or_update_pricing(): void
    {
        $this->getJson('/api/admin/pricing')->assertUnauthorized();
        $this->putJson('/api/admin/pricing/comprehensive', ['price' => 50000])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')
            ->putJson('/api/admin/pricing/comprehensive', ['price' => 50000])
            ->assertForbidden();
    }

    public function test_administrator_can_update_price_and_old_row_is_deactivated(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $old = PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/pricing/comprehensive', ['price' => 55000]);

        $response->assertCreated()
            ->assertJsonPath('tier', 'comprehensive')
            ->assertJsonPath('price', 55000)
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('pricing_plans', ['id' => $old->id, 'is_active' => false]);
        $this->assertSame(55000, PricingPlan::activePriceFor('comprehensive'));
    }

    public function test_price_change_is_audit_logged(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/pricing/master', ['price' => 150000])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'ubah_harga',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_invalid_tier_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/pricing/rapid', ['price' => 1000])
            ->assertNotFound();
    }

    public function test_negative_price_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/pricing/comprehensive', ['price' => -1])
            ->assertUnprocessable();
    }

    public function test_index_lists_price_history(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        PricingPlan::setPriceFor('comprehensive', 40000, $admin);
        PricingPlan::setPriceFor('comprehensive', 49000, $admin);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/pricing');

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}
