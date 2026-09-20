<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_KEY = 'xnd_development_test-secret-key';

    private const CALLBACK_TOKEN = 'test-callback-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.xendit.secret_key' => self::SECRET_KEY,
            'services.xendit.callback_token' => self::CALLBACK_TOKEN,
            'services.xendit.base_url' => 'https://api.xendit.co',
        ]);

        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
        PricingPlan::create(['tier' => 'master', 'price' => 149000, 'is_active' => true]);
    }

    public function test_unconfigured_xendit_returns_clean_503_not_raw_500(): void
    {
        // Kondisi nyata sekarang di dev - XENDIT_SECRET_KEY masih kosong.
        config(['services.xendit.secret_key' => null]);

        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/samples/{$sample->id}/payment");

        $response->assertStatus(503)->assertJsonStructure(['message']);
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('trace');
    }

    public function test_owner_can_create_payment_for_comprehensive_sample(): void
    {
        Http::fake([
            'api.xendit.co/v2/invoices' => Http::response([
                'id' => 'inv-abc123',
                'external_id' => 'whatever',
                'status' => 'PENDING',
                'invoice_url' => 'https://checkout.xendit.co/web/abc123',
                'expiry_date' => '2026-01-01T00:00:00.000Z',
            ], 200),
        ]);

        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/samples/{$sample->id}/payment");

        $response->assertCreated()
            ->assertJsonPath('payment_url', 'https://checkout.xendit.co/web/abc123')
            ->assertJsonStructure(['invoice_number']);

        $this->assertDatabaseHas('payments', [
            'sample_id' => $sample->id,
            'amount' => 49000,
            'status' => 'pending',
            'xendit_invoice_id' => 'inv-abc123',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.xendit.co/v2/invoices'
                && $request->hasHeader('Authorization')
                && str_starts_with($request->header('Authorization')[0], 'Basic ');
        });
    }

    public function test_valid_discount_code_reduces_charged_amount(): void
    {
        Http::fake([
            'api.xendit.co/v2/invoices' => Http::response([
                'id' => 'inv-disc', 'external_id' => 'whatever', 'status' => 'PENDING',
                'invoice_url' => 'https://checkout.xendit.co/web/disc',
                'expiry_date' => '2026-01-01T00:00:00.000Z',
            ], 200),
        ]);

        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        $discount = DiscountCode::create(['code' => 'HEMAT10', 'type' => 'percentage', 'value' => 10]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment", ['discount_code' => 'hemat10']);

        $response->assertCreated();
        $this->assertDatabaseHas('payments', [
            'sample_id' => $sample->id,
            'base_amount' => 49000,
            'amount' => 44100,
            'discount_code_id' => $discount->id,
        ]);
    }

    public function test_invalid_discount_code_rejects_payment_creation(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment", ['discount_code' => 'TIDAKADA']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_non_owner_cannot_create_payment(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $owner->id, 'created_by' => $owner->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment")
            ->assertForbidden();
    }

    public function test_rapid_tier_cannot_be_paid(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'rapid', 'status' => 'completed',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment")
            ->assertStatus(422);
    }

    public function test_already_paid_sample_rejects_new_payment(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-EXISTING',
            'amount' => 49000, 'status' => 'paid',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/payment")
            ->assertStatus(422);
    }

    public function test_notification_marks_payment_paid_on_valid_callback_token(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        $payment = Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-NOTIF-1',
            'amount' => 49000, 'status' => 'pending',
        ]);

        $body = ['external_id' => 'INV-NOTIF-1', 'status' => 'PAID', 'amount' => 49000];

        $response = $this->withHeaders(['x-callback-token' => self::CALLBACK_TOKEN])
            ->postJson('/api/payments/notification', $body);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_notification_increments_discount_usage_once_on_success(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        $discount = DiscountCode::create(['code' => 'INCR1', 'type' => 'fixed', 'value' => 5000, 'used_count' => 0]);
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-NOTIF-DISC',
            'base_amount' => 49000, 'amount' => 44000, 'discount_code_id' => $discount->id, 'status' => 'pending',
        ]);

        $body = ['external_id' => 'INV-NOTIF-DISC', 'status' => 'PAID', 'amount' => 44000];
        $headers = ['x-callback-token' => self::CALLBACK_TOKEN];

        // Kirim notifikasi PAID dua kali (Xendit bisa retry) - kuota cuma
        // boleh naik sekali, bukan dua kali.
        $this->withHeaders($headers)->postJson('/api/payments/notification', $body)->assertOk();
        $this->withHeaders($headers)->postJson('/api/payments/notification', $body)->assertOk();

        $this->assertSame(1, $discount->fresh()->used_count);
    }

    public function test_notification_rejects_invalid_callback_token(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        $payment = Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-NOTIF-2',
            'amount' => 49000, 'status' => 'pending',
        ]);

        $body = ['external_id' => 'INV-NOTIF-2', 'status' => 'PAID', 'amount' => 49000];

        $response = $this->withHeaders(['x-callback-token' => 'not-the-right-token'])
            ->postJson('/api/payments/notification', $body);

        $response->assertStatus(400);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }

    public function test_notification_rejects_missing_callback_token(): void
    {
        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-NOTIF-3',
            'amount' => 49000, 'status' => 'pending',
        ]);

        $body = ['external_id' => 'INV-NOTIF-3', 'status' => 'PAID', 'amount' => 49000];

        $this->postJson('/api/payments/notification', $body)->assertStatus(400);
    }

    public function test_notification_credits_token_balance_on_successful_token_purchase(): void
    {
        // Satu Callback URL Xendit dipakai untuk Payment (sample klien) DAN
        // TokenPurchase (token grafolog) - dibedakan lewat prefix invoice_number.
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 2]);
        $purchase = TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 10, 'unit_price' => 5000, 'base_amount' => 50000,
            'amount' => 50000, 'status' => 'pending', 'invoice_number' => 'TOKEN-NOTIF-1',
        ]);

        $body = ['external_id' => 'TOKEN-NOTIF-1', 'status' => 'PAID', 'amount' => 50000];

        $response = $this->withHeaders(['x-callback-token' => self::CALLBACK_TOKEN])
            ->postJson('/api/payments/notification', $body);

        $response->assertOk();
        $this->assertDatabaseHas('token_purchases', ['id' => $purchase->id, 'status' => 'paid']);
        $this->assertSame(12, $grafolog->fresh()->token_balance);
        $this->assertDatabaseHas('token_ledger_entries', [
            'user_id' => $grafolog->id, 'type' => 'purchase', 'delta' => 10,
            'balance_after' => 12, 'token_purchase_id' => $purchase->id,
        ]);
    }

    public function test_notification_does_not_double_credit_token_balance_on_retried_success(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 0]);
        $purchase = TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 5, 'unit_price' => 5000, 'base_amount' => 25000,
            'amount' => 25000, 'status' => 'pending', 'invoice_number' => 'TOKEN-NOTIF-2',
        ]);

        $body = ['external_id' => 'TOKEN-NOTIF-2', 'status' => 'PAID', 'amount' => 25000];
        $headers = ['x-callback-token' => self::CALLBACK_TOKEN];

        $this->withHeaders($headers)->postJson('/api/payments/notification', $body)->assertOk();
        $this->withHeaders($headers)->postJson('/api/payments/notification', $body)->assertOk();

        $this->assertSame(5, $grafolog->fresh()->token_balance);
        $this->assertDatabaseCount('token_ledger_entries', 1);
    }
}
