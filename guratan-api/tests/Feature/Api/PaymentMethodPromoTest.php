<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Promo dibatasi ke grup metode bayar -> halaman bayar DOKU dibatasi ke channel terkait (2026-10-19). */
class PaymentMethodPromoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.bypass' => false, 'services.doku.client_id' => 'cid', 'services.doku.secret_key' => 'sk']);
        Http::fake(['*' => Http::response(['response' => ['payment' => ['url' => 'https://pay.test/x', 'token_id' => 't', 'expired_date' => '2026']]], 200)]);
    }

    private function order(User $client, ?string $code)
    {
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/orders', [
            'product_id' => Product::where('kode', 'konseling')->value('id'), 'discount_code' => $code,
        ])->assertCreated()->json('id');

        return $id;
    }

    private function sentMethods(): ?array
    {
        $req = Http::recorded()->last()[0] ?? null;

        return $req?->data()['payment']['payment_method_types'] ?? null;
    }

    public function test_code_restricted_to_ewallet_limits_checkout_channels_and_rejects_other_choice(): void
    {
        DiscountCode::create(['code' => 'EWALLET10', 'type' => 'percentage', 'value' => 10, 'payment_methods' => ['ewallet']]);
        $client = User::factory()->create(['role' => 'user']);
        $id = $this->order($client, 'EWALLET10');

        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/payment", ['payment_method' => 'va'])->assertStatus(422);
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/payment")->assertCreated();
        $this->assertSame(config('payment_methods.ewallet.types'), $this->sentMethods());
    }

    public function test_unrestricted_code_honours_buyer_choice_and_default_is_unrestricted(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $id = $this->order($client, null);
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/payment", ['payment_method' => 'qris'])->assertCreated();
        $this->assertSame(['QRIS'], $this->sentMethods());

        $id2 = $this->order($client, null);
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id2}/payment")->assertCreated();
        $this->assertNull($this->sentMethods());
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id2}/payment", ['payment_method' => 'bitcoin'])->assertStatus(422);
    }

    public function test_admin_edits_methods_preview_reports_them_and_list_is_public(): void
    {
        $code = DiscountCode::create(['code' => 'QRIS5', 'type' => 'fixed', 'value' => 5000]);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/discount-codes/{$code->id}", ['payment_methods' => ['qris', 'card']])
            ->assertOk()->assertJsonPath('payment_methods', ['qris', 'card']);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/discount-codes/{$code->id}", ['payment_methods' => ['xx']])->assertStatus(422);

        $client = User::factory()->create(['role' => 'user']);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders/preview', [
            'product_id' => Product::where('kode', 'konseling')->value('id'), 'discount_code' => 'QRIS5',
        ])->assertOk()->assertJsonPath('code_payment_methods', ['qris', 'card']);
        $this->getJson('/api/payment-methods')->assertOk()->assertJsonFragment(['key' => 'ewallet', 'label' => 'E-Wallet']);
    }
}
