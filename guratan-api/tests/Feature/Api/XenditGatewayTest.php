<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Gateway ganda: Xendit sebagai alternatif DOKU (pilihan pembeli, webhook, daftar gateway). */
class XenditGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK_TOKEN = 'cb-token-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.bypass' => false,
            'payment.gateway' => 'doku',
            'services.doku.client_id' => 'MCH-TEST-0001',
            'services.doku.secret_key' => 'doku-secret',
            'services.doku.base_url' => 'https://api-sandbox.doku.com',
            'services.xendit.secret_key' => 'xnd_development_test',
            'services.xendit.callback_token' => self::CALLBACK_TOKEN,
            'services.xendit.base_url' => 'https://api.xendit.co',
        ]);

        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
    }

    private function sampleFor(User $user): HandwritingSample
    {
        return HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
    }

    public function test_gateway_list_shows_only_configured_with_default_first(): void
    {
        $this->getJson('/api/payment-gateways')
            ->assertOk()
            ->assertJson(['default' => 'doku', 'available' => ['doku', 'xendit']]);

        config(['payment.gateway' => 'xendit', 'services.doku.client_id' => null]);

        $this->getJson('/api/payment-gateways')->assertJson(['default' => 'xendit', 'available' => ['xendit']]);
    }

    public function test_buyer_can_pay_with_xendit_and_basic_auth_is_used(): void
    {
        Http::fake(['api.xendit.co/*' => Http::response(['id' => 'inv-xnd-1', 'invoice_url' => 'https://checkout.xendit.co/web/inv-xnd-1'], 200)]);
        $user = User::factory()->create();
        $sample = $this->sampleFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment", ['gateway' => 'xendit'])
            ->assertCreated()
            ->assertJsonPath('payment_url', 'https://checkout.xendit.co/web/inv-xnd-1');

        $this->assertDatabaseHas('payments', [
            'sample_id' => $sample->id, 'gateway' => 'xendit', 'gateway_ref' => 'inv-xnd-1', 'amount' => 49000,
        ]);
        Http::assertSent(fn ($r) => str_starts_with($r->header('Authorization')[0], 'Basic ') && $r['amount'] === 49000);
    }

    public function test_default_gateway_is_used_when_buyer_does_not_choose(): void
    {
        config(['payment.gateway' => 'xendit']);
        Http::fake(['api.xendit.co/*' => Http::response(['id' => 'inv-2', 'invoice_url' => 'https://checkout.xendit.co/web/inv-2'], 200)]);
        $user = User::factory()->create();
        $sample = $this->sampleFor($user);

        $this->actingAs($user, 'sanctum')->postJson("/api/samples/{$sample->id}/payment")->assertCreated();

        $this->assertDatabaseHas('payments', ['sample_id' => $sample->id, 'gateway' => 'xendit']);
    }

    public function test_choosing_unconfigured_gateway_is_rejected(): void
    {
        config(['services.xendit.secret_key' => null]);
        $user = User::factory()->create();
        $sample = $this->sampleFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment", ['gateway' => 'xendit'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('gateway');
    }

    public function test_xendit_rejection_returns_clean_503(): void
    {
        Http::fake(['api.xendit.co/*' => Http::response(['message' => 'INVALID_API_KEY'], 401)]);
        $user = User::factory()->create();
        $sample = $this->sampleFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment", ['gateway' => 'xendit'])
            ->assertStatus(503)
            ->assertJsonMissingPath('trace');
    }

    public function test_bypass_only_when_no_gateway_configured(): void
    {
        config(['payment.bypass' => null, 'services.doku.client_id' => null, 'services.xendit.secret_key' => 'xnd_development_test']);
        $this->assertFalse(\App\Services\Payment\PaymentMode::bypassed());

        config(['services.xendit.secret_key' => null]);
        $this->assertTrue(\App\Services\Payment\PaymentMode::bypassed());
    }

    public function test_xendit_webhook_marks_payment_paid(): void
    {
        $user = User::factory()->create();
        $payment = Payment::create([
            'sample_id' => $this->sampleFor($user)->id, 'invoice_number' => 'INV-X-1',
            'amount' => 49000, 'status' => 'pending', 'gateway' => 'xendit',
        ]);

        $this->withHeaders(['X-Callback-Token' => self::CALLBACK_TOKEN])
            ->postJson('/api/payments/xendit-notification', ['external_id' => 'INV-X-1', 'status' => 'PAID'])
            ->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_xendit_webhook_expired_marks_failed_and_paid_is_final(): void
    {
        $user = User::factory()->create();
        $pending = Payment::create(['sample_id' => $this->sampleFor($user)->id, 'invoice_number' => 'INV-X-2', 'amount' => 49000, 'status' => 'pending']);
        $paid = Payment::create(['sample_id' => $this->sampleFor($user)->id, 'invoice_number' => 'INV-X-3', 'amount' => 49000, 'status' => 'paid', 'paid_at' => now()]);
        $h = ['X-Callback-Token' => self::CALLBACK_TOKEN];

        $this->withHeaders($h)->postJson('/api/payments/xendit-notification', ['external_id' => 'INV-X-2', 'status' => 'EXPIRED'])->assertOk();
        $this->withHeaders($h)->postJson('/api/payments/xendit-notification', ['external_id' => 'INV-X-3', 'status' => 'EXPIRED'])->assertOk();

        $this->assertSame('failed', $pending->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
    }

    public function test_xendit_webhook_rejects_wrong_or_missing_token(): void
    {
        Payment::create(['sample_id' => $this->sampleFor(User::factory()->create())->id, 'invoice_number' => 'INV-X-4', 'amount' => 1, 'status' => 'pending']);

        $this->postJson('/api/payments/xendit-notification', ['external_id' => 'INV-X-4', 'status' => 'PAID'])->assertStatus(400);
        $this->withHeaders(['X-Callback-Token' => 'salah'])
            ->postJson('/api/payments/xendit-notification', ['external_id' => 'INV-X-4', 'status' => 'PAID'])->assertStatus(400);

        config(['services.xendit.callback_token' => null]);
        $this->withHeaders(['X-Callback-Token' => ''])
            ->postJson('/api/payments/xendit-notification', ['external_id' => 'INV-X-4', 'status' => 'PAID'])->assertStatus(400);
        $this->assertSame('pending', Payment::where('invoice_number', 'INV-X-4')->first()->status);
    }

    public function test_xendit_webhook_unknown_invoice_is_404(): void
    {
        $this->withHeaders(['X-Callback-Token' => self::CALLBACK_TOKEN])
            ->postJson('/api/payments/xendit-notification', ['external_id' => 'NOPE', 'status' => 'PAID'])
            ->assertNotFound();
    }

    public function test_xendit_webhook_credits_token_purchase_once(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $purchase = TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 5, 'unit_price' => 10000, 'base_amount' => 50000,
            'amount' => 50000, 'currency' => 'IDR', 'status' => 'pending', 'invoice_number' => 'TOKEN-X-1', 'gateway' => 'xendit',
        ]);
        $h = ['X-Callback-Token' => self::CALLBACK_TOKEN];

        $this->withHeaders($h)->postJson('/api/payments/xendit-notification', ['external_id' => 'TOKEN-X-1', 'status' => 'SETTLED'])->assertOk();
        $this->withHeaders($h)->postJson('/api/payments/xendit-notification', ['external_id' => 'TOKEN-X-1', 'status' => 'PAID'])->assertOk();

        $this->assertSame('paid', $purchase->fresh()->status);
        $this->assertSame(5, (int) \App\Models\TokenLedgerEntry::where('user_id', $grafolog->id)->sum('delta'));
    }
}
