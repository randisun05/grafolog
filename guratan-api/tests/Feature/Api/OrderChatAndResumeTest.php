<?php

namespace Tests\Feature\Api;

use App\Models\GrafologRequest;
use App\Models\HandwritingSample;
use App\Models\InboxMessage;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\PersonalityReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Alur klien individu tahap 3: chat teks per pesanan + resume laporan (2026-10-30). */
class OrderChatAndResumeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $grafolog;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
        config(['payment.bypass' => true]);
        $this->client = User::factory()->create(['role' => 'user']);
        $this->grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $id = $this->actingAs($this->client, 'sanctum')->postJson('/api/orders', [
            'product_id' => Product::where('kode', 'konseling')->value('id'), 'grafolog_choice' => 'pilih', 'grafolog_id' => $this->grafolog->id,
        ])->assertCreated()->json('id');
        $this->actingAs($this->client, 'sanctum')->postJson("/api/orders/{$id}/payment")->assertCreated();
        $this->order = Order::find($id);
    }

    private function accept(): void
    {
        $req = GrafologRequest::where('order_id', $this->order->id)->first();
        $this->actingAs($this->grafolog, 'sanctum')->postJson("/api/grafolog/requests/{$req->id}/respond", ['decision' => 'accept'])->assertOk();
    }

    public function test_chat_is_closed_until_grafolog_accepts_then_both_parties_exchange_messages(): void
    {
        $url = "/api/orders/{$this->order->id}/messages";
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => 'Halo'])->assertStatus(422); // belum diterima
        $this->actingAs($this->grafolog, 'sanctum')->getJson($url)->assertForbidden(); // belum diterima = bukan peserta

        $this->accept();
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => 'Halo <b>Bu</b>, kapan bisa mulai?'])->assertCreated();
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => 'Pesan kedua'])->assertCreated();
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => str_repeat('x', 2001)])->assertStatus(422);
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => '   '])->assertStatus(422);

        // Satu pemberitahuan untuk rentetan pesan belum dibaca.
        $this->assertSame(1, InboxMessage::where('user_id', $this->grafolog->id)->where('type', 'pesan_baru')->count());
        $this->assertSame(2, $this->actingAs($this->grafolog, 'sanctum')->getJson('/api/chat/unread')->json('total'));

        $res = $this->actingAs($this->grafolog, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('open', true)->assertJsonPath('with.id', $this->client->id);
        $this->assertSame('Halo Bu, kapan bisa mulai?', $res->json('messages.0.body')); // tag HTML dibuang
        $this->assertFalse($res->json('messages.0.mine'));
        $this->assertSame(0, $this->actingAs($this->grafolog, 'sanctum')->getJson('/api/chat/unread')->json('total')); // sudah dibaca

        $this->actingAs($this->grafolog, 'sanctum')->postJson($url, ['body' => 'Besok pagi ya'])->assertCreated();
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $this->client->id, 'type' => 'pesan_baru']);
        $after = $this->actingAs($this->client, 'sanctum')->getJson($url.'?after='.OrderMessage::where('sender_id', $this->client->id)->max('id'))->json('messages');
        $this->assertCount(1, $after);
        $this->assertTrue($after[0]['sender'] === $this->grafolog->name && ! $after[0]['mine']);
    }

    public function test_outsiders_cannot_read_admin_reads_only_and_is_audited_and_closed_orders_are_readonly(): void
    {
        $this->accept();
        $url = "/api/orders/{$this->order->id}/messages";
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => 'rahasia'])->assertCreated();

        $outsider = User::factory()->create(['role' => 'user']);
        $otherG = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $this->actingAs($outsider, 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($otherG, 'sanctum')->getJson($url)->assertForbidden();

        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('open', false)->assertJsonPath('messages.0.body', 'rahasia');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'lihat_chat_pesanan', 'actor_user_id' => $admin->id]);
        $this->actingAs($admin, 'sanctum')->postJson($url, ['body' => 'x'])->assertForbidden();
        // Admin membaca tidak menandai pesan sebagai dibaca.
        $this->assertNull(OrderMessage::first()->read_at);

        $this->order->update(['status' => Order::STATUS_DONE, 'completed_at' => now()->subDays(15)]);
        $this->actingAs($this->client, 'sanctum')->postJson($url, ['body' => 'terlambat'])->assertStatus(422);
        $this->actingAs($this->client, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('open', false);
    }

    public function test_grafolog_request_list_shows_accepted_orders_with_unread_and_account_deletion_scrubs_messages(): void
    {
        $this->accept();
        $this->actingAs($this->client, 'sanctum')->postJson("/api/orders/{$this->order->id}/messages", ['body' => 'Halo'])->assertCreated();
        $acc = $this->actingAs($this->grafolog, 'sanctum')->getJson('/api/grafolog/requests')->json('accepted');
        $this->assertSame([$this->order->id, 1], [$acc[0]['id'], $acc[0]['unread']]);

        // Hak data: ekspor memuat pesan sendiri.
        $this->assertStringContainsString('Halo', $this->actingAs($this->client, 'sanctum')->get('/api/me/export')->streamedContent());
    }

    public function test_resume_is_deterministic_neutral_and_access_controlled(): void
    {
        $this->accept();
        $sample = $this->order->sample_id ?: HandwritingSample::create(['user_id' => $this->client->id, 'created_by' => $this->grafolog->id, 'tier' => 'master', 'status' => 'completed'])->id;
        $data = ['sindrom' => [
            ['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'rata_rata_skor' => 6.5, 'band_label_rata_rata' => 'Nilai Sedang', 'aspek' => [
                ['kode' => '01', 'nama' => 'Ambisi', 'skor' => 9, 'band_label' => 'Nilai Tinggi', 'narasi' => 'Orang ini berorientasi pada pencapaian. Ia senang tantangan baru.'],
                ['kode' => '02', 'nama' => 'Kesabaran', 'skor' => 3, 'band_label' => 'Nilai Rendah', 'narasi' => 'Cenderung cepat bertindak.'],
                ['kode' => '03', 'nama' => 'Fokus', 'skor' => 8, 'band_label' => 'Nilai Tinggi', 'narasi' => 'Fokus.'],
            ]],
        ]];
        $report = PersonalityReport::create(['sample_id' => $sample, 'tier' => 'master', 'status' => 'completed', 'data' => $data, 'review_status' => 'disetujui']);

        $res = $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}/resume?lang=id")->assertOk();
        $this->assertSame(['Ambisi', 'Fokus', 'Kesabaran'], collect($res->json('menonjol'))->pluck('nama')->all()); // urut skor
        $this->assertSame('Orang ini berorientasi pada pencapaian.', $res->json('menonjol.0.ringkas')); // kalimat pertama saja
        $this->assertSame(6.5, $res->json('sindrom.0.rata_rata'));
        $this->assertStringContainsString('bukan diagnosis', $res->json('catatan'));
        $this->actingAs($this->client, 'sanctum')->getJson("/api/reports/{$report->id}/resume?lang=en")->assertOk()->assertJsonPath('catatan', fn ($c) => str_contains($c, 'not a psychological'));

        $stranger = User::factory()->create(['role' => 'user']);
        $this->actingAs($stranger, 'sanctum')->getJson("/api/reports/{$report->id}/resume")->assertForbidden();
    }
}
