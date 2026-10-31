<?php

namespace Tests\Feature\Api\Admin;

use App\Models\DiscountCode;
use App\Models\HandwritingSample;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentRecapControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeSample(User $client, string $tier = 'comprehensive'): HandwritingSample
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $project = Project::create(['source' => 'grafolog', 'created_by' => $grafolog->id]);

        return HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $client->id,
            'created_by' => $grafolog->id, 'tier' => $tier, 'status' => 'pending',
        ]);
    }

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/admin/recap/payments')->assertUnauthorized();
        $this->get('/api/admin/recap/payments/export')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/recap/payments')->assertForbidden();
    }

    public function test_lists_with_relations(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $client = User::factory()->create(['name' => 'Klien Bayar']);
        $sample = $this->makeSample($client, 'master');
        $discount = DiscountCode::create([
            'code' => 'MASTER5', 'type' => 'percentage', 'value' => 5, 'created_by' => $admin->id,
        ]);
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'PAY-1', 'base_amount' => 99000,
            'discount_code_id' => $discount->id, 'amount' => 94050, 'status' => 'paid', 'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/payments');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sample.user.name', 'Klien Bayar')
            ->assertJsonPath('data.0.discount_code.code', 'MASTER5')
            ->assertJsonPath('data.0.amount', 94050);
    }

    public function test_filters_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $clientA = User::factory()->create(['name' => 'Cari Pembayar']);
        $clientB = User::factory()->create();
        $sampleA = $this->makeSample($clientA);
        $sampleB = $this->makeSample($clientB);

        Payment::create([
            'sample_id' => $sampleA->id, 'invoice_number' => 'PAY-A', 'base_amount' => 49000,
            'amount' => 49000, 'status' => 'paid', 'paid_at' => now(),
        ]);
        Payment::create([
            'sample_id' => $sampleB->id, 'invoice_number' => 'PAY-B', 'base_amount' => 49000,
            'amount' => 49000, 'status' => 'pending',
        ]);

        $paidResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/payments?status=paid');
        $paidResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'PAY-A');

        $searchResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/payments?search=Cari');
        $searchResponse->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.invoice_number', 'PAY-A');
    }

    public function test_export_returns_csv_and_logs_audit(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $client = User::factory()->create(['name' => 'Klien CSV Bayar']);
        $sample = $this->makeSample($client);
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'PAY-CSV', 'base_amount' => 49000,
            'amount' => 49000, 'status' => 'paid', 'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/admin/recap/payments/export');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Klien CSV Bayar', $content);
        $this->assertStringContainsString('PAY-CSV', $content);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ekspor_rekap_pembayaran', 'actor_user_id' => $admin->id]);
    }
}
