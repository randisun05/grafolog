<?php

namespace Tests\Feature\Api;

use App\Mail\NotificationMail;
use App\Models\Company;
use App\Models\InboxMessage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Kotak masuk notifikasi + email + broadcast admin (2026-10-18). */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
        config(['payment.bypass' => true]);
    }

    public function test_paid_b2c_order_notifies_client_by_inbox_and_email(): void
    {
        $client = User::factory()->create(['role' => 'user']);
        $id = $this->actingAs($client, 'sanctum')->postJson('/api/orders', ['product_id' => Product::where('kode', 'konseling')->value('id')])->json('id');
        $this->actingAs($client, 'sanctum')->postJson("/api/orders/{$id}/payment")->assertCreated();

        $this->assertDatabaseHas('inbox_messages', ['user_id' => $client->id, 'type' => 'pembayaran']);
        Mail::assertQueued(NotificationMail::class, fn ($m) => $m->hasTo($client->email));
    }

    public function test_b2b_batch_sends_one_notice_per_party_and_candidates_get_inbox_only(): void
    {
        $company = Company::create(['name' => 'PT Uji']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $ids = collect(['a', 'b'])->map(fn ($n) => $this->actingAs($hr, 'sanctum')->postJson('/api/hr/candidates', [
            'name' => $n, 'email' => "$n@calon.test", 'consent_confirmed' => true,
        ])->json('id'))->all();
        $batch = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => $ids, 'grafolog_id' => $grafolog->id,
        ])->json();
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertCreated();

        $this->assertSame(1, InboxMessage::where('user_id', $hr->id)->where('type', 'pembayaran')->count());
        $this->assertSame(1, InboxMessage::where('user_id', $grafolog->id)->where('type', 'penugasan')->count()); // bukan per kandidat
        $this->assertSame(2, InboxMessage::where('type', 'tugas_asesmen')->count());
        Mail::assertNotQueued(NotificationMail::class, fn ($m) => $m->hasTo('a@calon.test')); // kandidat tidak diemail
    }

    public function test_registrations_notify_admins_and_decisions_notify_applicant(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->postJson('/api/auth/register-company', [
            'company_name' => 'PT Baru', 'industry' => 'IT', 'employee_range' => '11-50', 'company_phone' => '1', 'address' => 'Jl 1',
            'name' => 'Rina', 'email' => 'rina@baru.test', 'phone' => '08', 'password' => 'RahasiaHR123', 'password_confirmation' => 'RahasiaHR123', 'consent' => true,
        ])->assertCreated();
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $admin->id, 'type' => 'verifikasi_baru']);

        $hr = User::where('email', 'rina@baru.test')->first();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/company-applications/{$hr->company_id}/decision", ['decision' => 'approve'])->assertOk();
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $hr->id, 'type' => 'verifikasi', 'title' => 'Perusahaan diverifikasi']);
    }

    public function test_user_reads_and_manages_only_their_own_inbox(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $mine = InboxMessage::create(['user_id' => $a->id, 'type' => 'x', 'title' => 'T1', 'body' => 'b']);
        InboxMessage::create(['user_id' => $a->id, 'type' => 'x', 'title' => 'T2', 'body' => 'b']);
        $theirs = InboxMessage::create(['user_id' => $b->id, 'type' => 'x', 'title' => 'Rahasia', 'body' => 'b']);

        $this->actingAs($a, 'sanctum')->getJson('/api/inbox')->assertOk()->assertJsonPath('unread', 2)->assertJsonCount(2, 'messages.data');
        $this->actingAs($a, 'sanctum')->getJson('/api/inbox/unread-count')->assertJsonPath('unread', 2);
        $this->actingAs($a, 'sanctum')->postJson("/api/inbox/{$mine->id}/read")->assertOk();
        $this->actingAs($a, 'sanctum')->getJson('/api/inbox?unread=1')->assertJsonCount(1, 'messages.data');
        $this->actingAs($a, 'sanctum')->postJson("/api/inbox/{$theirs->id}/read")->assertNotFound();
        $this->actingAs($a, 'sanctum')->postJson('/api/inbox/read-all')->assertJsonPath('unread', 0);
        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_admin_broadcast_targets_roles_and_optionally_emails(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $clients = User::factory()->count(2)->create(['role' => 'user']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->postJson('/api/admin/broadcasts', ['title' => 'x', 'body' => 'y'])->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/broadcasts', [
            'title' => 'Maintenance', 'body' => 'Sistem istirahat malam ini.', 'target_roles' => ['user'], 'send_email' => true,
        ])->assertCreated()->assertJsonPath('recipients', 2);

        $this->assertSame(2, InboxMessage::where('type', 'broadcast')->count());
        $this->assertDatabaseMissing('inbox_messages', ['user_id' => $grafolog->id, 'type' => 'broadcast']);
        Mail::assertQueued(NotificationMail::class, 2);

        // Tanpa target_roles = semua pengguna; tanpa email = hanya kotak masuk.
        Mail::fake();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/broadcasts', ['title' => 'Halo', 'body' => 'Semua'])->assertCreated()->assertJsonPath('recipients', 4);
        Mail::assertNothingQueued();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/broadcasts')->assertOk()->assertJsonCount(2);
        $this->assertCount(2, $clients);
    }
}
