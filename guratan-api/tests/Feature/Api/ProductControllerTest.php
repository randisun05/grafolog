<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_endpoint_returns_only_active_products_ordered(): void
    {
        Product::create(['code' => 'master', 'name' => 'Master', 'is_active' => true, 'sort_order' => 10]);
        Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive', 'is_active' => true, 'sort_order' => 0]);
        Product::create(['code' => 'rapid', 'name' => 'Rapid', 'is_active' => false, 'sort_order' => 99]);

        $response = $this->getJson('/api/products');

        $response->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.code', 'comprehensive')
            ->assertJsonPath('1.code', 'master');
    }

    public function test_public_endpoint_requires_no_auth(): void
    {
        $this->getJson('/api/products')->assertOk();
    }
}
