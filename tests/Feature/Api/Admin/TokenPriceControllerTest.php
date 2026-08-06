<?php

namespace Tests\Feature\Api\Admin;

use App\Models\TokenPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenPriceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_or_update_token_price(): void
    {
        $this->getJson('/api/admin/token-price')->assertUnauthorized();
        $this->putJson('/api/admin/token-price', ['price_per_token' => 5000])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->putJson('/api/admin/token-price', ['price_per_token' => 5000])
            ->assertForbidden();
    }

    public function test_administrator_can_set_price_and_old_row_is_deactivated(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $old = TokenPrice::create(['price_per_token' => 4000, 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-price', ['price_per_token' => 5000]);

        $response->assertCreated()
            ->assertJsonPath('price_per_token', 5000)
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('token_prices', ['id' => $old->id, 'is_active' => false]);
        $this->assertSame(5000, TokenPrice::current());
    }

    public function test_price_change_is_audit_logged(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-price', ['price_per_token' => 5000])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'ubah_harga_token',
            'actor_user_id' => $admin->id,
        ]);
    }

    public function test_negative_price_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/token-price', ['price_per_token' => -1])
            ->assertUnprocessable();
    }

    public function test_index_lists_price_history(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        TokenPrice::setPrice(4000, $admin);
        TokenPrice::setPrice(5000, $admin);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/token-price');

        $response->assertOk()->assertJsonCount(2, 'data');
    }
}
