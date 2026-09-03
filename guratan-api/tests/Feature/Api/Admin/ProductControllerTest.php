<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_products(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->postJson('/api/admin/products', [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_index_includes_inactive_products(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive', 'is_active' => true, 'sort_order' => 0]);
        Product::create(['code' => 'rapid', 'name' => 'Rapid', 'is_active' => false, 'sort_order' => 99]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/products');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_administrator_can_create_product(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/products', [
            'code' => 'deluxe', 'name' => 'Deluxe', 'description' => 'Tier baru', 'sort_order' => 20,
        ]);

        $response->assertCreated()->assertJsonPath('code', 'deluxe');
        $this->assertDatabaseHas('products', ['code' => 'deluxe', 'name' => 'Deluxe']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_produk']);
    }

    public function test_create_rejects_duplicate_code_and_bad_slug(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/products', [
            'code' => 'comprehensive', 'name' => 'Dup',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/products', [
            'code' => 'Not A Slug!', 'name' => 'Bad',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_administrator_can_update_name_description_sort_order_and_toggle_active(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $product = Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive', 'sort_order' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/products/{$product->id}", [
            'name' => 'Comprehensive (Updated)', 'description' => 'Deskripsi baru', 'sort_order' => 5, 'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Comprehensive (Updated)')
            ->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_produk', 'target_id' => $product->id]);
    }

    public function test_update_silently_ignores_code_field(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $product = Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/products/{$product->id}", [
            'code' => 'renamed', 'name' => 'Comprehensive',
        ]);

        $response->assertOk();
        $this->assertSame('comprehensive', $product->fresh()->code);
    }
}
