<?php

namespace Tests\Feature\Api\Admin;

use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProducts;
use Tests\TestCase;

class DiscountCodeControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProducts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProducts();
    }

    public function test_guest_cannot_manage_discount_codes(): void
    {
        $this->getJson('/api/admin/discount-codes')->assertUnauthorized();
        $this->postJson('/api/admin/discount-codes', [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/admin/discount-codes', ['code' => 'X', 'type' => 'percentage', 'value' => 10])
            ->assertForbidden();
    }

    public function test_administrator_can_create_percentage_code(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/discount-codes', [
            'code' => 'guratan10',
            'type' => 'percentage',
            'value' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('code', 'GURATAN10') // dinormalisasi uppercase
            ->assertJsonPath('type', 'percentage')
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_kode_diskon', 'actor_user_id' => $admin->id]);
    }

    public function test_percentage_value_over_100_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/discount-codes', [
            'code' => 'TOOBIG', 'type' => 'percentage', 'value' => 150,
        ])->assertUnprocessable()->assertJsonValidationErrors('value');
    }

    public function test_duplicate_code_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        DiscountCode::create(['code' => 'DUP10', 'type' => 'fixed', 'value' => 5000]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/discount-codes', [
            'code' => 'dup10', 'type' => 'fixed', 'value' => 1000,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_administrator_can_deactivate_code(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $code = DiscountCode::create(['code' => 'OFF10', 'type' => 'fixed', 'value' => 5000, 'is_active' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/discount-codes/{$code->id}", ['is_active' => false]);

        $response->assertOk()->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'nonaktifkan_kode_diskon']);
    }

    public function test_index_lists_codes(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        DiscountCode::create(['code' => 'A', 'type' => 'fixed', 'value' => 1000]);
        DiscountCode::create(['code' => 'B', 'type' => 'percentage', 'value' => 5]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/discount-codes')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    // Sistem Products data-driven, Fase 2b: bukti applicable_tiers.* dibaca
    // dari Product::activeCodes() dinamis, TAPI 'token' tetap diterima
    // sebagai literal (pseudo-tier pembelian token, bukan baris Product).
    public function test_applicable_tiers_accepts_new_product_and_token_literal(): void
    {
        Product::create(['code' => 'deluxe', 'name' => 'Deluxe', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/discount-codes', [
            'code' => 'DELUXE10', 'type' => 'fixed', 'value' => 5000,
            'applicable_tiers' => ['deluxe', 'token'],
        ]);

        $response->assertCreated()->assertJsonPath('applicable_tiers', ['deluxe', 'token']);
    }

    public function test_applicable_tiers_rejects_inactive_product(): void
    {
        Product::where('code', 'comprehensive')->update(['is_active' => false]);
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/discount-codes', [
            'code' => 'OFFTIER', 'type' => 'fixed', 'value' => 5000,
            'applicable_tiers' => ['comprehensive'],
        ])->assertUnprocessable()->assertJsonValidationErrors('applicable_tiers.0');
    }
}
