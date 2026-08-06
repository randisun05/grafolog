<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = 'MCH-TEST-0001';
    private const SECRET_KEY = 'test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.doku.client_id' => self::CLIENT_ID,
            'services.doku.secret_key' => self::SECRET_KEY,
            'services.doku.base_url' => 'https://api-sandbox.doku.com',
        ]);

        PricingPlan::create(['tier' => 'comprehensive', 'price' => 49000, 'is_active' => true]);
        PricingPlan::create(['tier' => 'master', 'price' => 149000, 'is_active' => true]);
    }

    private function dokuSignature(string $requestId, string $timestamp, string $target, string $rawBody, string $clientId = self::CLIENT_ID): string
    {
        $digest = base64_encode(hash('sha256', $rawBody, true));
        $component = implode("\n", [
            'Client-Id:'.$clientId,
            'Request-Id:'.$requestId,
            'Request-Timestamp:'.$timestamp,
            'Request-Target:'.$target,
            'Digest:'.$digest,
        ]);

        return 'HMACSHA256='.base64_encode(hash_hmac('sha256', $component, self::SECRET_KEY, true));
    }

    public function test_unconfigured_doku_returns_clean_503_not_raw_500(): void
    {
        // Kondisi nyata sekarang di dev - DOKU_CLIENT_ID/SECRET masih kosong.
        config(['services.doku.client_id' => null, 'services.doku.secret_key' => null]);

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
            'api-sandbox.doku.com/*' => Http::response([
                'message' => ['SUCCESS'],
                'response' => [
                    'order' => ['amount' => '49000', 'invoice_number' => 'whatever'],
                    'payment' => [
                        'token_id' => 'tok-abc123',
                        'url' => 'https://sandbox.doku.com/checkout-link-v2/abc123',
                        'expired_date' => '20260101000000',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/samples/{$sample->id}/payment");

        $response->assertCreated()
            ->assertJsonPath('payment_url', 'https://sandbox.doku.com/checkout-link-v2/abc123')
            ->assertJsonStructure(['invoice_number']);

        $this->assertDatabaseHas('payments', [
            'sample_id' => $sample->id,
            'amount' => 49000,
            'status' => 'pending',
            'doku_token_id' => 'tok-abc123',
        ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Client-Id', self::CLIENT_ID)
                && $request->hasHeader('Signature')
                && str_starts_with($request->header('Signature')[0], 'HMACSHA256=');
        });
    }

    public function test_valid_discount_code_reduces_charged_amount(): void
    {
        Http::fake([
            'api-sandbox.doku.com/*' => Http::response([
                'message' => ['SUCCESS'],
                'response' => [
                    'order' => ['amount' => '44100', 'invoice_number' => 'whatever'],
                    'payment' => ['token_id' => 'tok-disc', 'url' => 'https://sandbox.doku.com/checkout-link-v2/disc', 'expired_date' => '20260101000000'],
                ],
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

    public function test_notification_marks_payment_paid_on_valid_signature(): void
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

        $body = ['order' => ['invoice_number' => 'INV-NOTIF-1', 'amount' => '49000'], 'transaction' => ['status' => 'SUCCESS']];
        $rawBody = json_encode($body);
        $requestId = 'req-notif-1';
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature = $this->dokuSignature($requestId, $timestamp, '/api/payments/notification', $rawBody);

        $response = $this->withHeaders([
            'Client-Id' => self::CLIENT_ID,
            'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp,
            'Signature' => $signature,
        ])->postJson('/api/payments/notification', $body);

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
        $payment = Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-NOTIF-DISC',
            'base_amount' => 49000, 'amount' => 44000, 'discount_code_id' => $discount->id, 'status' => 'pending',
        ]);

        $body = ['order' => ['invoice_number' => 'INV-NOTIF-DISC', 'amount' => '44000'], 'transaction' => ['status' => 'SUCCESS']];
        $rawBody = json_encode($body);
        $requestId = 'req-notif-disc';
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature = $this->dokuSignature($requestId, $timestamp, '/api/payments/notification', $rawBody);
        $headers = [
            'Client-Id' => self::CLIENT_ID, 'Request-Id' => $requestId,
            'Request-Timestamp' => $timestamp, 'Signature' => $signature,
        ];

        // Kirim notifikasi SUCCESS dua kali (DOKU bisa retry) - kuota cuma
        // boleh naik sekali, bukan dua kali.
        $this->withHeaders($headers)->postJson('/api/payments/notification', $body)->assertOk();
        $this->withHeaders($headers)->postJson('/api/payments/notification', $body)->assertOk();

        $this->assertSame(1, $discount->fresh()->used_count);
    }

    public function test_notification_rejects_invalid_signature(): void
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

        $body = ['order' => ['invoice_number' => 'INV-NOTIF-2', 'amount' => '49000'], 'transaction' => ['status' => 'SUCCESS']];

        $response = $this->withHeaders([
            'Client-Id' => self::CLIENT_ID,
            'Request-Id' => 'req-notif-2',
            'Request-Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Signature' => 'HMACSHA256=not-the-right-signature',
        ])->postJson('/api/payments/notification', $body);

        $response->assertStatus(400);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    }
}
