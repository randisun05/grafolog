<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kode promo aktif per saluran: klien / grafolog (token) / b2b (2026-10-19). */
class DiscountAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    public function test_client_code_rejected_for_b2b_and_token_quote_but_accepted_for_client(): void
    {
        DiscountCode::create(['code' => 'KLIEN10', 'type' => 'percentage', 'value' => 10, 'audiences' => ['client']]);
        $kode = Product::where('kode', 'konseling')->value('id');
        $client = User::factory()->create(['role' => 'user']);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders/preview', ['product_id' => $kode, 'discount_code' => 'KLIEN10'])
            ->assertOk()->assertJsonPath('code_valid', true);

        $hr = User::factory()->create(['role' => 'hr', 'company_id' => Company::create(['name' => 'PT'])->id]);
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders/preview', ['product' => 'paket-rekrutmen', 'count' => 1, 'discount_code' => 'KLIEN10'])
            ->assertOk()->assertJsonPath('code_valid', false);
    }

    public function test_null_audiences_means_everywhere_and_admin_can_change_channels(): void
    {
        $code = DiscountCode::create(['code' => 'SEMUA', 'type' => 'fixed', 'value' => 1000]);
        $this->assertTrue($code->isValidFor('token', 'grafolog'));
        $this->assertTrue($code->isValidFor('konseling', 'b2b'));

        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/discount-codes/{$code->id}", ['audiences' => ['grafolog']])
            ->assertOk()->assertJsonPath('audiences', ['grafolog']);
        $code->refresh();
        $this->assertTrue($code->isValidFor('token', 'grafolog'));
        $this->assertFalse($code->isValidFor('konseling', 'client'));
        $this->assertFalse($code->isValidFor('konseling', 'b2b'));

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/discount-codes/{$code->id}", ['audiences' => []])->assertOk();
        $this->assertNull($code->fresh()->audiences);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/discount-codes/{$code->id}", ['audiences' => ['x']])->assertStatus(422);
    }

    public function test_public_promos_filtered_by_audience(): void
    {
        DiscountCode::create(['code' => 'PUBKLIEN', 'type' => 'fixed', 'value' => 1, 'is_public' => true, 'audiences' => ['client']]);
        DiscountCode::create(['code' => 'PUBB2B', 'type' => 'fixed', 'value' => 1, 'is_public' => true, 'audiences' => ['b2b']]);
        $this->assertSame(['PUBKLIEN'], collect($this->getJson('/api/promos')->json('codes'))->pluck('code')->all());
        $this->assertSame(['PUBB2B'], collect($this->getJson('/api/promos?audience=b2b')->json('codes'))->pluck('code')->all());
    }
}
