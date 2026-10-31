<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\PricingPlan;
use App\Models\TokenPrice;
use App\Models\User;
use App\Services\Payment\PaymentMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // tidak boleh ada panggilan DOKU sama sekali
        config(['services.doku.client_id' => null, 'services.doku.secret_key' => null, 'payment.bypass' => null]);
        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
        TokenPrice::create(['price_per_token' => 5000, 'is_active' => true]);
    }

    private function clientSample(): array
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        return [$user, $sample];
    }

    public function test_bypass_is_automatic_when_doku_is_unconfigured(): void
    {
        $this->assertTrue(PaymentMode::bypassed());

        // "Terkonfigurasi" = client id DAN secret terisi (PaymentGateway::isConfigured()).
        config(['services.doku.client_id' => 'abc', 'services.doku.secret_key' => 'def']);
        $this->assertFalse(PaymentMode::bypassed());

        config(['payment.bypass' => 'true']);
        $this->assertTrue(PaymentMode::bypassed());
    }

    public function test_bypass_is_never_active_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['payment.bypass' => true]);

        $this->assertFalse(PaymentMode::bypassed());
    }

    public function test_sample_payment_is_marked_paid_without_calling_doku(): void
    {
        [$user, $sample] = $this->clientSample();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment")
            ->assertCreated()
            ->assertJson(['paid' => true, 'bypass' => true]);

        $this->assertTrue($sample->fresh()->payments()->where('status', 'paid')->exists());
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'pembayaran_mode_uji']);
        Http::assertNothingSent();
    }

    public function test_token_purchase_credits_tokens_without_calling_doku(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/tokens/purchase', ['quantity' => 4])
            ->assertCreated()
            ->assertJson(['paid' => true, 'bypass' => true]);

        $this->assertSame(4, $grafolog->fresh()->token_balance);
        $this->assertDatabaseHas('token_purchases', ['user_id' => $grafolog->id, 'status' => 'paid']);
        Http::assertNothingSent();
    }
}
