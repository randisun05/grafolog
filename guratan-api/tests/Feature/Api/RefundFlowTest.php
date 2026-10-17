<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\PersonalityReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** SOP pembelian/refund/pembatalan (Fase E, 2026-10-17). */
class RefundFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['payment.bypass' => true]);
    }

    private function paidOrder(User $client, string $kode = 'master'): Order
    {
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/orders', [
            'product_id' => Product::where('kode', $kode)->value('id'),
        ])->assertCreated()->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/payment")->assertCreated();

        return Order::findOrFail($id);
    }

    public function test_client_requests_refund_and_admin_approves(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $order = $this->paidOrder($client);
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($client, 'sanctum')->getJson('/api/orders')->assertJsonPath('0.refundable', true);
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'pendek'])
            ->assertUnprocessable();
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Saya salah memilih produk.'])
            ->assertOk()->assertJsonPath('refund_status', 'diajukan')->assertJsonPath('refundable', false);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/refunds')->assertOk()->assertJsonCount(1);
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['decision' => 'approve', 'note' => 'Dana dikembalikan via DOKU.'])
            ->assertOk()->assertJsonPath('status', 'dikembalikan');

        $fresh = $order->fresh();
        $this->assertFalse($fresh->sample->isPaid()); // skoring terkunci lagi (402)
        $this->assertSame(['dibatalkan'], $fresh->deliveries->pluck('status')->unique()->values()->all());
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['decision' => 'approve', 'note' => 'x'])
            ->assertUnprocessable();
    }

    public function test_refund_not_allowed_after_report_released_and_reject_needs_request(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $order = $this->paidOrder($client);
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['decision' => 'reject', 'note' => 'x'])
            ->assertUnprocessable();

        PersonalityReport::create(['sample_id' => $order->sample_id, 'tier' => 'master', 'status' => 'completed',
            'review_status' => PersonalityReport::REVIEW_APPROVED, 'data' => []]);
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Berubah pikiran setelah membaca.'])
            ->assertUnprocessable();
    }

    public function test_rejected_request_keeps_order_running(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $order = $this->paidOrder($client, 'konseling');
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Jadwal saya tidak cocok.'])->assertOk();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['decision' => 'reject', 'note' => 'Jadwal bisa diatur ulang.'])
            ->assertOk()->assertJsonPath('status', 'diproses')->assertJsonPath('refund_status', 'ditolak');
        $this->actingAs($client, 'sanctum')->getJson('/api/orders')->assertJsonPath('0.refund_note', 'Jadwal bisa diatur ulang.');
    }

    public function test_other_users_cannot_request_refund(): void
    {
        $order = $this->paidOrder(User::factory()->create(['role' => 'user']));
        $other = User::factory()->create(['role' => 'user']);
        $this->actingAs($other, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Bukan pesanan saya.'])
            ->assertForbidden();
    }
}
