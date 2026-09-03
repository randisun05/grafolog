<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_codes_excludes_inactive_products(): void
    {
        Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive', 'is_active' => true, 'sort_order' => 0]);
        Product::create(['code' => 'rapid', 'name' => 'Rapid', 'is_active' => false, 'sort_order' => 99]);

        $this->assertSame(['comprehensive'], Product::activeCodes());
    }

    public function test_active_codes_respects_sort_order(): void
    {
        Product::create(['code' => 'master', 'name' => 'Master', 'is_active' => true, 'sort_order' => 10]);
        Product::create(['code' => 'comprehensive', 'name' => 'Comprehensive', 'is_active' => true, 'sort_order' => 0]);

        $this->assertSame(['comprehensive', 'master'], Product::activeCodes());
    }

    public function test_active_codes_is_empty_array_when_no_products_exist(): void
    {
        $this->assertSame([], Product::activeCodes());
    }
}
