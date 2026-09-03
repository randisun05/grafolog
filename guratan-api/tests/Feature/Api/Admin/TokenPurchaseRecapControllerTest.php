<?php

namespace Tests\Feature\Api\Admin;

use App\Models\DiscountCode;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenPurchaseRecapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/admin/recap/token-purchases')->assertUnauthorized();
        $this->get('/api/admin/recap/token-purchases/export')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/recap/token-purchases')->assertForbidden();
    }

    public function test_lists_with_relations(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'name' => 'Grafolog Beli']);
        $discount = DiscountCode::create([
            'code' => 'HEMAT10', 'type' => 'fixed', 'value' => 5000, 'created_by' => $admin->id,
        ]);
        TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 10, 'unit_price' => 5000,
            'base_amount' => 50000, 'discount_code_id' => $discount->id, 'amount' => 45000,
            'status' => 'paid', 'invoice_number' => 'TOK-1', 'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/token-purchases');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Grafolog Beli')
            ->assertJsonPath('data.0.discount_code.code', 'HEMAT10')
            ->assertJsonPath('data.0.amount', 45000);
    }

    public function test_filters_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafologA = User::factory()->create(['role' => 'grafolog', 'name' => 'Cari Saya']);
        $grafologB = User::factory()->create(['role' => 'grafolog']);

        TokenPurchase::create([
            'user_id' => $grafologA->id, 'quantity' => 5, 'unit_price' => 5000,
            'base_amount' => 25000, 'amount' => 25000, 'status' => 'paid',
            'invoice_number' => 'TOK-A', 'paid_at' => now(),
        ]);
        TokenPurchase::create([
            'user_id' => $grafologB->id, 'quantity' => 5, 'unit_price' => 5000,
            'base_amount' => 25000, 'amount' => 25000, 'status' => 'pending',
            'invoice_number' => 'TOK-B',
        ]);

        $paidResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/token-purchases?status=paid');
        $paidResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'TOK-A');

        $searchResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/token-purchases?search=Cari');
        $searchResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'TOK-A');
    }

    public function test_export_returns_csv_and_logs_audit(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'name' => 'Grafolog CSV Token']);
        TokenPurchase::create([
            'user_id' => $grafolog->id, 'quantity' => 3, 'unit_price' => 5000,
            'base_amount' => 15000, 'amount' => 15000, 'status' => 'paid',
            'invoice_number' => 'TOK-CSV', 'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/admin/recap/token-purchases/export');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Grafolog CSV Token', $content);
        $this->assertStringContainsString('TOK-CSV', $content);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ekspor_rekap_token_purchase', 'actor_user_id' => $admin->id]);
    }
}
