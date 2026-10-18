<?php

namespace Tests\Feature\Api;

use App\Mail\NotificationMail;
use App\Models\Assignment;
use App\Models\Broadcast;
use App\Models\CvField;
use App\Models\CvGroup;
use App\Models\CvValue;
use App\Models\HandwritingSample;
use App\Models\InboxMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Hak subjek data: persetujuan ulang, ekspor, hapus akun, opt-out email (2026-10-19). */
class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
    }

    public function test_needs_consent_until_current_version_is_accepted_and_candidates_accept_themselves(): void
    {
        $old = User::factory()->create(['role' => 'user', 'consent_version' => '2026-10', 'consent_source' => 'self']);
        $this->actingAs($old, 'sanctum')->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.needs_consent', true);
        $this->actingAs($old, 'sanctum')->postJson('/api/me/accept-terms', [])->assertStatus(422);
        $this->actingAs($old, 'sanctum')->postJson('/api/me/accept-terms', ['accept' => true])->assertOk()->assertJsonPath('user.needs_consent', false);

        $candidate = User::factory()->create(['role' => 'user', 'consent_version' => User::CONSENT_VERSION, 'consent_source' => 'hr']);
        $this->assertTrue($candidate->needs_consent);
        $this->actingAs($candidate, 'sanctum')->postJson('/api/me/accept-terms', ['accept' => true])->assertOk();
        $this->assertFalse($candidate->fresh()->needs_consent);
    }

    public function test_export_contains_own_data_only(): void
    {
        $a = User::factory()->create(['role' => 'user', 'name' => 'Pemilik A']);
        $b = User::factory()->create(['role' => 'user', 'name' => 'Orang B']);
        InboxMessage::create(['user_id' => $a->id, 'type' => 'x', 'title' => 'Milik A', 'body' => 'b']);
        InboxMessage::create(['user_id' => $b->id, 'type' => 'x', 'title' => 'Milik B', 'body' => 'b']);
        $res = $this->actingAs($a, 'sanctum')->get('/api/me/export')->assertOk();
        $json = $res->streamedContent();
        $this->assertStringContainsString('Pemilik A', $json);
        $this->assertStringContainsString('Milik A', $json);
        $this->assertStringNotContainsString('Milik B', $json);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ekspor_data_pribadi', 'actor_user_id' => $a->id]);
    }

    public function test_delete_account_anonymizes_and_blocks_active_obligations(): void
    {
        $user = User::factory()->create(['role' => 'user', 'password' => 'rahasia123', 'email' => 'asli@x.test']);
        $g = CvGroup::create(['name' => 'P']);
        $f = CvField::create(['cv_group_id' => $g->id, 'key' => 'k', 'label' => 'K', 'type' => 'text', 'applies_to' => 'all']);
        CvValue::create(['user_id' => $user->id, 'cv_field_id' => $f->id, 'value' => 'isi']);
        InboxMessage::create(['user_id' => $user->id, 'type' => 'x', 'title' => 't', 'body' => 'b']);
        $product = Product::where('kode', 'konseling')->first();
        $unpaid = Order::create(['user_id' => $user->id, 'product_id' => $product->id, 'status' => Order::STATUS_UNPAID, 'base_amount' => 1, 'amount' => 1]);

        $this->actingAs($user, 'sanctum')->postJson('/api/me/delete', ['password' => 'salah', 'confirm' => 'HAPUS'])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson('/api/me/delete', ['password' => 'rahasia123', 'confirm' => 'hapus'])->assertStatus(422);

        $processing = Order::create(['user_id' => $user->id, 'product_id' => $product->id, 'status' => Order::STATUS_PROCESSING, 'base_amount' => 1, 'amount' => 1]);
        $this->actingAs($user, 'sanctum')->postJson('/api/me/delete', ['password' => 'rahasia123', 'confirm' => 'HAPUS'])->assertStatus(422);
        $processing->update(['status' => Order::STATUS_DONE]);

        $this->actingAs($user, 'sanctum')->postJson('/api/me/delete', ['password' => 'rahasia123', 'confirm' => 'HAPUS'])->assertOk();
        $user->refresh();
        $this->assertSame('Pengguna Terhapus', $user->name);
        $this->assertStringEndsWith('@deleted.invalid', $user->email);
        $this->assertNotNull($user->anonymized_at);
        $this->assertSame(0, CvValue::where('user_id', $user->id)->count());
        $this->assertSame(0, InboxMessage::where('user_id', $user->id)->count());
        $this->assertSame(Order::STATUS_CANCELLED, $unpaid->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_grafolog_with_active_assignment_or_tokens_needs_confirmation(): void
    {
        $g = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified', 'password' => 'rahasia123', 'token_balance' => 5]);
        $sample = HandwritingSample::create(['user_id' => User::factory()->create()->id, 'created_by' => $g->id, 'tier' => 'master', 'status' => 'pending']);
        $assign = Assignment::create(['sample_id' => $sample->id, 'grafolog_id' => $g->id, 'assigned_by' => $g->id, 'status' => 'assigned']);
        $body = ['password' => 'rahasia123', 'confirm' => 'HAPUS'];

        $this->actingAs($g, 'sanctum')->postJson('/api/me/delete', $body)->assertStatus(422);
        $assign->update(['status' => 'completed']);
        $this->actingAs($g, 'sanctum')->postJson('/api/me/delete', $body)->assertStatus(422); // token hangus perlu konfirmasi
        $this->actingAs($g, 'sanctum')->postJson('/api/me/delete', $body + ['forfeit_tokens' => true])->assertOk();
        $this->assertSame(0, $g->fresh()->token_balance);

        $admin = User::factory()->create(['role' => 'administrator', 'password' => 'rahasia123']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/me/delete', $body)->assertStatus(422);
    }

    public function test_email_opt_out_skips_mail_but_not_essential_or_critical(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user, 'sanctum')->putJson('/api/profile', ['email_notifications' => false])->assertOk();
        $user->refresh();
        $notifier = app(Notifier::class);

        $notifier->send($user, 'penugasan', 'Biasa', 'isi');
        Mail::assertNothingQueued();
        $this->assertSame(1, InboxMessage::where('user_id', $user->id)->count()); // kotak masuk tetap
        $notifier->send($user, 'pembayaran', 'Lunas', 'isi');
        Mail::assertQueued(NotificationMail::class, 1);

        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/broadcasts', ['title' => 'Insiden', 'body' => 'Pemberitahuan', 'target_roles' => ['user'], 'is_critical' => true])
            ->assertCreated();
        Mail::assertQueued(NotificationMail::class, 2);
        $this->assertTrue(Broadcast::first()->is_critical);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $user->id, 'type' => 'insiden']);
    }
}
