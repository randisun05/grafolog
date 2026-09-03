<?php

namespace Tests\Concerns;

use App\Models\Product;

/**
 * Product::activeCodes() (dipakai StoreSampleRequest/ImportCandidatesRequest/
 * PreviewPricingRequest/StoreDiscountCodeRequest/Admin\PricingController/
 * Admin\TokenCostController/Api\TokenController - lihat guratan-api/CLAUDE.md
 * "Sistem Products data-driven") baca dari tabel products, yang TIDAK
 * otomatis terisi oleh RefreshDatabase (seeder tidak jalan otomatis per
 * test). Test manapun yang lewat endpoint HTTP membawa tier "comprehensive"/
 * "master" dan mengharap sukses harus panggil seedProducts() dulu di
 * setUp()-nya, sama pola dengan SeedsGrafologiKb.
 */
trait SeedsProducts
{
    protected function seedProducts(): void
    {
        Product::firstOrCreate(['code' => 'comprehensive'], ['name' => 'Comprehensive', 'is_active' => true, 'sort_order' => 0]);
        Product::firstOrCreate(['code' => 'master'], ['name' => 'Master', 'is_active' => true, 'sort_order' => 10]);
    }
}
