<?php

namespace Tests\Feature\Api;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PersonalityReport;
use App\Models\Product;
use App\Models\ServiceDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Katalog produk + pesanan + layanan pendukung (Fase B, 2026-10-17). */
class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['payment.bypass' => true]);
    }

    private function master(): Product
    {
        return Product::where('kode', 'master')->firstOrFail();
    }

    public function test_public_catalog_lists_b2c_products_without_comprehensive(): void
    {
        $res = $this->getJson('/api/products')->assertOk()->json();
        $kodes = collect($res)->pluck('kode');

        $this->assertSame('master', $kodes->first()); // unggulan dulu
        $this->assertContains('konseling', $kodes);
        $this->assertNotContains('paket-rekrutmen', $kodes); // khusus B2B
        $this->assertNotContains('comprehensive', $kodes);
        $this->assertSame(['grafologi', 'konsultasi'], collect($res[0]['components'])->pluck('tipe')->all());
        $this->assertSame(149000, $res[0]['price']);
    }

    public function test_client_orders_master_pays_in_test_mode_and_gets_services(): void
    {
        $client = User::factory()->create(['role' => 'user']);

        $order = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id])
            ->assertCreated()->assertJsonPath('status', 'menunggu_pembayaran')->json();
        $this->assertNotNull($order['sample_id']);
        $this->assertDatabaseHas('handwriting_samples', ['id' => $order['sample_id'], 'tier' => 'master', 'user_id' => $client->id]);

        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order['id']}/payment")
            ->assertCreated()->assertJsonPath('paid', true);

        $fresh = Order::find($order['id']);
        $this->assertSame('diproses', $fresh->status);
        $this->assertTrue($fresh->sample->isPaid()); // gerbang 402 skoring terbuka
        $this->assertSame(['konsultasi'], $fresh->deliveries->map(fn ($d) => $d->component->tipe)->all());

        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order['id']}/payment")->assertStatus(422);
    }

    public function test_order_by_grafolog_referred_client_is_auto_assigned_on_payment(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $client = User::factory()->create(['role' => 'user', 'referred_by' => $grafolog->id, 'acquisition_source' => 'grafolog']);

        $order = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id])
            ->assertCreated()->json();
        $this->assertDatabaseMissing('assignments', ['sample_id' => $order['sample_id']]); // belum lunas

        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order['id']}/payment")->assertCreated();

        $this->assertDatabaseHas('assignments', ['sample_id' => $order['sample_id'], 'grafolog_id' => $grafolog->id, 'status' => 'assigned']);
        $this->assertTrue(Order::find($order['id'])->sample->isPaid()); // tetap lewat gerbang bayar
    }

    public function test_order_by_self_registered_client_or_unverified_referrer_is_not_auto_assigned(): void
    {
        $pending = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'pending']);
        $referred = User::factory()->create(['role' => 'user', 'referred_by' => $pending->id]);
        $own = User::factory()->create(['role' => 'user']);

        foreach ([$referred, $own] as $client) {
            $order = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id])->assertCreated()->json();
            $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$order['id']}/payment")->assertCreated();
            $this->assertDatabaseMissing('assignments', ['sample_id' => $order['sample_id']]);
        }
    }

    public function test_b2b_product_and_inactive_product_cannot_be_ordered_by_client(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $b2b = Product::where('kode', 'paket-rekrutmen')->first();
        $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $b2b->id])->assertStatus(422);

        Product::where('kode', 'konseling')->update(['is_active' => false]);
        $konseling = Product::where('kode', 'konseling')->first();
        $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $konseling->id])->assertStatus(422);
    }

    public function test_discount_is_scoped_to_product_kode(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        DiscountCode::create(['code' => 'MASTER20', 'type' => 'percentage', 'value' => 20, 'applicable_tiers' => ['master']]);
        $konseling = Product::where('kode', 'konseling')->first();

        $this->actingAs($client, 'sanctum')->postJson('/api/orders/preview', ['product_id' => $this->master()->id, 'discount_code' => 'master20'])
            ->assertOk()->assertJsonPath('final_price', 119200)->assertJsonPath('code_valid', true);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders/preview', ['product_id' => $konseling->id, 'discount_code' => 'MASTER20'])
            ->assertOk()->assertJsonPath('code_valid', false)->assertJsonPath('final_price', 250000);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $konseling->id, 'discount_code' => 'MASTER20'])
            ->assertStatus(422);
    }

    public function test_service_delivery_assign_schedule_report_and_order_completion(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $other = User::factory()->create(['role' => 'grafolog']);
        $konseling = Product::where('kode', 'konseling')->first();

        $orderId = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $konseling->id])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$orderId}/payment")->assertCreated();
        $delivery = ServiceDelivery::where('order_id', $orderId)->firstOrFail();
        $this->assertNull(Order::find($orderId)->sample_id); // konseling tanpa tulisan tangan

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/assign", ['grafolog_id' => $grafolog->id])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/assign", ['grafolog_id' => $grafolog->id])
            ->assertOk()->assertJsonPath('status', 'ditugaskan');

        $this->actingAs($other, 'sanctum')->putJson("/api/service-deliveries/{$delivery->id}/schedule", ['scheduled_at' => now()->addDay()->toIso8601String()])->assertForbidden();
        $this->actingAs($grafolog, 'sanctum')->putJson("/api/service-deliveries/{$delivery->id}/schedule", [
            'scheduled_at' => now()->addDay()->toIso8601String(), 'meeting_url' => 'https://meet.example.com/abc',
        ])->assertOk()->assertJsonPath('status', 'dijadwalkan');

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/report", ['report' => ['keluhan_utama' => 'x']])
            ->assertStatus(422);
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/report", ['report' => [
            'keluhan_utama' => 'Kecemasan kerja', 'intervensi_diberikan' => 'CBT ringan', 'perkembangan' => 4, 'tindak_lanjut' => 'Sesi lanjutan',
        ]])->assertOk()->assertJsonPath('status', 'selesai');

        $this->assertSame('selesai', Order::find($orderId)->status);
        $this->actingAs($client, 'sanctum')->getJson("/api/service-deliveries/{$delivery->id}")->assertOk()->assertJsonPath('report.perkembangan', 4);
        $this->actingAs($other, 'sanctum')->getJson("/api/service-deliveries/{$delivery->id}")->assertForbidden();
        $this->actingAs($client, 'sanctum')->getJson('/api/orders')->assertOk()->assertJsonPath('0.deliveries.0.has_report', true);
    }

    public function test_master_order_completes_only_after_report_approved_and_consultation_reported(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $orderId = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$orderId}/payment");
        $order = Order::find($orderId);

        $delivery = $order->deliveries->first();
        $delivery->update(['assigned_to' => $grafolog->id, 'status' => 'ditugaskan']);
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/service-deliveries/{$delivery->id}/report", ['report' => [
            'ringkasan' => 'a', 'temuan_utama' => 'b', 'rekomendasi' => 'c',
        ]])->assertOk();
        $this->assertSame('diproses', $order->fresh()->status); // laporan grafologi belum disetujui

        $report = PersonalityReport::create([
            'sample_id' => $order->sample_id, 'tier' => 'master', 'status' => 'completed', 'generated_at' => now(), 'data' => ['sindrom' => []],
        ]);
        $report->update(['review_status' => 'disetujui']);

        $this->assertSame('selesai', $order->fresh()->status);
    }

    public function test_client_can_cancel_unpaid_order_only(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/cancel")->assertOk()->assertJsonPath('status', 'dibatalkan');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/payment")->assertStatus(422);

        $paidId = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$paidId}/payment");
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$paidId}/cancel")->assertStatus(422);

        $stranger = User::factory()->create(['role' => 'user']);
        $this->actingAs($stranger, 'sanctum')->postJson("/api/orders/{$paidId}/payment")->assertForbidden();
    }

    public function test_admin_manages_products_components_and_prices(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $p = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/products', [
            'kode' => 'Tes Minat Karier', 'nama' => 'Tes Minat Karier', 'kategori' => 'pendukung', 'price' => 75000,
        ])->assertCreated()->assertJsonPath('kode', 'tes-minat-karier')->json();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/products/{$p['id']}/components", ['tipe' => 'konsultasi', 'nama' => 'Sesi Arah Karier'])
            ->assertCreated()->assertJsonPath('report_fields.0.key', 'ringkasan'); // template default
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/products/{$p['id']}/components", [
            'tipe' => 'lainnya', 'nama' => 'Kuis', 'report_fields' => [['key' => 'x', 'label' => 'X', 'type' => 'choice']],
        ])->assertStatus(422); // choice wajib options

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/pricing/tes-minat-karier', ['price' => 80000])->assertCreated();
        $listed = collect($this->actingAs($admin, 'sanctum')->getJson('/api/admin/products')->json())->firstWhere('kode', 'tes-minat-karier');
        $this->assertSame(80000, $listed['price']);

        // Produk yang sudah dipesan tidak bisa dihapus.
        $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/products/'.$this->master()->id)->assertNoContent();
    }

    public function test_product_with_orders_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $client = User::factory()->create(['role' => 'user']);
        $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => $this->master()->id]);

        $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/products/'.$this->master()->id)->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/products/'.$this->master()->id, ['kode' => 'master-baru'])->assertStatus(422);
    }

    public function test_public_promos_only_show_public_usable_codes(): void
    {
        DiscountCode::create(['code' => 'PUBLIK', 'type' => 'fixed', 'value' => 10000, 'is_public' => true, 'description' => 'Hemat 10rb']);
        DiscountCode::create(['code' => 'RAHASIA', 'type' => 'fixed', 'value' => 10000]);
        DiscountCode::create(['code' => 'HABIS', 'type' => 'fixed', 'value' => 10000, 'is_public' => true, 'max_uses' => 1, 'used_count' => 1]);

        $this->getJson('/api/promos')->assertOk()
            ->assertJsonCount(1, 'codes')->assertJsonPath('codes.0.code', 'PUBLIK')
            ->assertJsonPath('products.0.kode', 'master');
    }
}
