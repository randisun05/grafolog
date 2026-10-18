<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Refund hanya oleh administrator: kesalahan sistem, atau pesanan > 90 hari belum selesai (2026-10-18). */
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

    public function test_paid_orders_cannot_be_cancelled_or_refunded_by_the_client(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $order = $this->paidOrder($client);

        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order->id}/cancel")->assertUnprocessable();
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Berubah pikiran saja.'])->assertNotFound();
        $this->actingAs($client, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'kesalahan_sistem', 'note' => 'x'])->assertForbidden();
    }

    public function test_admin_refunds_for_system_error_and_locks_the_order(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $order = $this->paidOrder($client, 'konseling');
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'kesalahan_sistem'])->assertUnprocessable(); // catatan wajib
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'kesalahan_sistem', 'note' => 'Layanan tidak terproses.'])
            ->assertOk()->assertJsonPath('status', 'dikembalikan')->assertJsonPath('refund_reason', 'kesalahan_sistem');

        $this->assertSame(['dibatalkan'], $order->fresh()->deliveries->pluck('status')->unique()->values()->all());
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $client->id, 'type' => 'refund']);
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'kesalahan_sistem', 'note' => 'x'])->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/refunds')->assertJsonCount(1, 'refunded');
    }

    public function test_grafolog_late_refund_needs_90_days_and_unlocks_scoring_gate(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $order = $this->paidOrder($client);
        $admin = User::factory()->create(['role' => 'administrator']);
        $body = ['reason' => 'grafolog_terlambat', 'note' => 'Lebih dari 3 bulan.'];

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", $body)->assertUnprocessable();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/refunds')->assertJsonCount(0, 'overdue');

        $order->update(['paid_at' => now()->subDays(91)]);
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/refunds')->assertJsonCount(1, 'overdue');
        $this->assertTrue($order->fresh()->sample->isPaid());
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", $body)->assertOk();
        $this->assertFalse($order->fresh()->sample->isPaid());
    }

    public function test_unpaid_order_can_still_be_cancelled(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => Product::where('kode', 'master')->value('id')])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/cancel")->assertOk();
    }
}
