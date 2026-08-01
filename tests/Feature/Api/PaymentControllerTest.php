<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\Payment;
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
            'pricing.tiers.comprehensive' => 49000,
            'pricing.tiers.master' => 149000,
        ]);
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
