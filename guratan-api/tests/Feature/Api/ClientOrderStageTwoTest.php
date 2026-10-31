<?php

namespace Tests\Feature\Api;

use App\Mail\NotificationMail;
use App\Models\Assignment;
use App\Models\GrafologAvailability;
use App\Models\GrafologRequest;
use App\Models\InboxMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\GrafologMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Alur klien individu tahap 2: pilih grafolog, konfirmasi 24 jam, pilih ulang, rekomendasi admin (2026-10-30). */
class ClientOrderStageTwoTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
        config(['payment.bypass' => true]);
        $this->client = User::factory()->create(['role' => 'user']);
    }

    private function grafolog(string $name = 'G'): User
    {
        return User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified', 'name' => $name, 'city' => 'Bandung', 'specializations' => ['Konseling']]);
    }

    private function paidOrder(array $extra = [], string $kode = 'master'): Order
    {
        $id = $this->actingAs($this->client, 'sanctum')->postJson('/api/orders', [
            'product_id' => Product::where('kode', $kode)->value('id'), 'upload_deadline' => now()->addDays(2)->toDateString(),
        ] + $extra)->assertCreated()->json('id');
        $this->actingAs($this->client, 'sanctum')->postJson("/api/orders/{$id}/payment")->assertCreated();

        return Order::find($id);
    }

    public function test_chosen_grafolog_gets_24h_request_and_accepting_assigns_everything(): void
    {
        $g = $this->grafolog();
        $order = $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id]);

        $this->assertSame('menunggu_konfirmasi', $order->grafolog_state);
        $this->assertEqualsWithDelta(24 * 3600, $order->grafolog_deadline->diffInSeconds(now(), true), 120);
        $this->assertNull(Assignment::where('sample_id', $order->sample_id)->first()); // belum ditugaskan sebelum konfirmasi
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $g->id, 'type' => 'permintaan_grafolog']);
        Mail::assertQueued(NotificationMail::class, fn ($m) => $m->hasTo($g->email));

        $req = $this->actingAs($g, 'sanctum')->getJson('/api/grafolog/requests')->assertOk()->assertJsonPath('pending.0.order.id', $order->id)->json('pending.0.id');
        $this->actingAs($g, 'sanctum')->postJson("/api/grafolog/requests/{$req}/respond", ['decision' => 'accept'])->assertOk()->assertJsonPath('status', 'diterima');

        $order->refresh();
        $this->assertSame('diterima', $order->grafolog_state);
        $this->assertSame($g->id, $order->grafolog_id);
        $this->assertSame($g->id, Assignment::where('sample_id', $order->sample_id)->value('grafolog_id'));
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $this->client->id, 'title' => 'Grafolog menerima pesanan Anda']);
    }

    public function test_service_only_order_deliveries_are_assigned_on_acceptance(): void
    {
        $g = $this->grafolog();
        $order = $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id], 'konseling');
        $this->assertSame(['menunggu_penugasan'], $order->deliveries()->pluck('status')->unique()->all());

        $req = GrafologRequest::where('order_id', $order->id)->first();
        $this->actingAs($g, 'sanctum')->postJson("/api/grafolog/requests/{$req->id}/respond", ['decision' => 'accept'])->assertOk();
        $this->assertSame(['ditugaskan'], $order->deliveries()->pluck('status')->unique()->all());
        $this->assertSame($g->id, $order->deliveries()->first()->assigned_to);
    }

    public function test_decline_lets_client_pick_another_and_three_failures_go_to_admin_queue(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        [$a, $b, $c, $d] = [$this->grafolog('A'), $this->grafolog('B'), $this->grafolog('C'), $this->grafolog('D')];
        $order = $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $a->id]);

        $decline = function (User $g) use ($order) {
            $req = GrafologRequest::where('order_id', $order->id)->where('grafolog_id', $g->id)->where('status', 'menunggu')->firstOrFail();
            $this->actingAs($g, 'sanctum')->postJson("/api/grafolog/requests/{$req->id}/respond", ['decision' => 'decline', 'note' => 'penuh'])->assertOk();
        };
        $choose = fn (User $g) => $this->actingAs($this->client, 'sanctum')->postJson("/api/orders/{$order->id}/grafolog", ['grafolog_id' => $g->id]);

        $decline($a);
        $this->assertSame(['menunggu_pilih', 1, null], [$order->refresh()->grafolog_state, $order->grafolog_attempts, $order->grafolog_id]);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $this->client->id, 'title' => 'Pilih grafolog lain']);
        $choose($a)->assertStatus(422); // sudah menolak
        $choose($b)->assertOk()->assertJsonPath('grafolog_state', 'menunggu_konfirmasi');
        $decline($b);
        $choose($c)->assertOk();
        $decline($c); // gagal ke-3

        $order->refresh();
        $this->assertSame('menunggu_admin', $order->grafolog_state);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $admin->id, 'type' => 'perlu_penugasan']);
        $choose($d)->assertStatus(422); // sudah di antrean admin

        // Admin melihat rekomendasi (tanpa yang sudah menolak) dan menugaskan.
        $res = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/client-orders?state=menunggu_admin')->assertOk();
        $names = collect($res->json('0.recommendations'))->pluck('name')->all();
        $this->assertSame(['D'], $names);
        $this->assertCount(3, $res->json('0.history'));
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/client-orders/{$order->id}/assign", ['grafolog_id' => $d->id])->assertOk()->assertJsonPath('state', 'diterima');
        $this->assertSame($d->id, Assignment::where('sample_id', $order->sample_id)->value('grafolog_id'));
    }

    public function test_no_answer_within_24h_expires_and_sweep_releases(): void
    {
        $g = $this->grafolog();
        $order = $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id]);
        $this->travel(25)->hours();
        $this->artisan('schedule:test', ['--name' => 'konfirmasi-grafolog-sweep'])->assertExitCode(0);

        $order->refresh();
        $this->assertSame(['menunggu_pilih', 1], [$order->grafolog_state, $order->grafolog_attempts]);
        $this->assertSame('kedaluwarsa', GrafologRequest::first()->status);
        $req = GrafologRequest::first();
        $this->actingAs($g, 'sanctum')->postJson("/api/grafolog/requests/{$req->id}/respond", ['decision' => 'accept'])->assertStatus(422);
    }

    public function test_recommendation_choice_and_admin_sample_assignment_sync(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $g = $this->grafolog();
        $order = $this->paidOrder(); // default: rekomendasi
        $this->assertSame('menunggu_admin', $order->grafolog_state);
        $this->assertSame('rekomendasi', $order->grafolog_choice);

        // Penugasan lama (per sampel) tetap berfungsi dan menyinkronkan status pesanan.
        $this->actingAs($admin, 'sanctum')->postJson("/api/samples/{$order->sample_id}/assignment", ['grafolog_id' => $g->id])->assertCreated();
        $this->assertSame(['diterima', $g->id], [$order->refresh()->grafolog_state, $order->grafolog_id]);
    }

    public function test_selection_rules_availability_authorization_and_pre_payment_change(): void
    {
        $g = $this->grafolog('G1');
        $other = $this->grafolog('G2');
        $month = now()->format('Y-m');
        GrafologAvailability::create(['user_id' => $other->id, 'month' => $month, 'status' => 'libur']);

        $p = Product::where('kode', 'konseling')->value('id');
        $post = fn (array $x) => $this->actingAs($this->client, 'sanctum')->postJson('/api/orders', ['product_id' => $p] + $x);
        $post(['grafolog_choice' => 'pilih', 'grafolog_id' => $other->id])->assertStatus(422); // libur
        $post(['grafolog_choice' => 'pilih'])->assertStatus(422); // tanpa id
        $post(['grafolog_choice' => 'pilih', 'grafolog_id' => $this->client->id])->assertStatus(422); // bukan grafolog

        // Ubah pilihan sebelum bayar.
        $id = $post(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id])->assertCreated()->json('id');
        $this->actingAs($this->client, 'sanctum')->postJson("/api/orders/{$id}/grafolog", ['rekomendasi' => true])->assertOk()->assertJsonPath('grafolog_choice', 'rekomendasi');

        // Hanya pemilik; grafolog lain tidak boleh menjawab permintaan orang lain.
        $order = $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id]);
        $req = GrafologRequest::where('order_id', $order->id)->first();
        $this->actingAs($other, 'sanctum')->postJson("/api/grafolog/requests/{$req->id}/respond", ['decision' => 'accept'])->assertForbidden();
        $stranger = User::factory()->create(['role' => 'user']);
        $this->actingAs($stranger, 'sanctum')->postJson("/api/orders/{$order->id}/grafolog", ['rekomendasi' => true])->assertForbidden();
        $this->actingAs($this->client, 'sanctum')->postJson("/api/orders/{$order->id}/grafolog", ['rekomendasi' => true])->assertStatus(422); // sedang menunggu konfirmasi
    }

    public function test_directory_lists_bookable_grafologs_for_clients_only_and_workload_counts_pending_orders(): void
    {
        $g = $this->grafolog('Terbuka');
        $full = $this->grafolog('Penuh');
        GrafologAvailability::create(['user_id' => $full->id, 'month' => now()->format('Y-m'), 'status' => 'penuh']);

        $names = collect($this->actingAs($this->client, 'sanctum')->getJson('/api/grafologs/directory')->assertOk()->json('grafologs'))->pluck('name')->all();
        $this->assertContains('Terbuka', $names);
        $this->assertNotContains('Penuh', $names);
        $this->assertContains('Penuh', collect($this->actingAs($this->client, 'sanctum')->getJson('/api/grafologs/directory?all=1')->json('grafologs'))->pluck('name')->all());
        $this->actingAs($g, 'sanctum')->getJson('/api/grafologs/directory')->assertForbidden();

        // Kapasitas 1: pesanan yang menunggu konfirmasi sudah memakai slot.
        GrafologAvailability::create(['user_id' => $g->id, 'month' => now()->format('Y-m'), 'status' => 'tersedia', 'capacity' => 1]);
        $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id]);
        $this->assertFalse(GrafologAvailability::forMonth($g, now()->format('Y-m'))['bookable']);
        $this->actingAs($this->client, 'sanctum')->postJson('/api/orders', ['product_id' => Product::where('kode', 'konseling')->value('id'), 'grafolog_choice' => 'pilih', 'grafolog_id' => $g->id])->assertStatus(422);
    }

    public function test_grafolog_gets_one_reminder_when_twelve_hours_remain(): void
    {
        $g = $this->grafolog();
        $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id]);
        $svc = app(GrafologMatchingService::class);

        $this->travel(11)->hours();
        $this->assertSame(0, $svc->remind()); // sisa 13 jam: belum waktunya
        $this->travel(2)->hours(); // sisa ± 11 jam
        $this->assertSame(1, $svc->remind());
        $this->assertSame(0, $svc->remind()); // hanya sekali
        $this->assertSame(1, InboxMessage::where('user_id', $g->id)->where('type', 'pengingat_permintaan')->count());
        Mail::assertQueued(NotificationMail::class, fn ($m) => $m->hasTo($g->email));

        // Yang sudah dijawab tidak diingatkan lagi.
        $o2 = $this->paidOrder(['grafolog_choice' => 'pilih', 'grafolog_id' => $g->id]);
        GrafologRequest::where('order_id', $o2->id)->update(['status' => 'ditolak']);
        $this->travel(11)->hours();
        $this->assertSame(0, $svc->remind());
    }
}
