<?php

namespace Tests\Feature\Api;

use App\Jobs\SendReportCompletedNotification;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\TokenCost;
use App\Models\TokenLedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/** Fase D (2026-10-17): validasi supervisor opsional, dibayar token grafolog -> supervisor. */
class ReportValidationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @return array{0: PersonalityReport, 1: User, 2: User} */
    private function draft(int $balance = 0): array
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => $balance]);
        $client = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => 'master', 'status' => 'pending',
        ]);
        $skor = collect(range(1, 3))->map(fn ($i) => ['kode' => str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'skor' => 7])->all();
        $id = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores", ['skor' => $skor])
            ->assertCreated()->json('id');

        return [PersonalityReport::findOrFail($id), $grafolog, $client];
    }

    private function setFee(int $tokens): void
    {
        TokenCost::setTokensFor('validasi_supervisor', $tokens, User::factory()->create(['role' => 'administrator']));
    }

    public function test_new_report_is_a_hidden_draft_outside_the_supervisor_queue(): void
    {
        [$report, $grafolog, $client] = $this->draft();
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->review_status);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
        $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('review_status', 'draf');
        $this->actingAs($supervisor, 'sanctum')->getJson('/api/reviews')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/reports/{$report->id}/review", ['decision' => 'approve'])->assertUnprocessable();
        Queue::assertNotPushed(SendReportCompletedNotification::class);
    }

    public function test_grafolog_can_publish_without_supervisor(): void
    {
        [$report, $grafolog, $client] = $this->draft();

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/publish")
            ->assertOk()->assertJsonPath('review_status', 'disetujui')->assertJsonPath('validation', 'mandiri');

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('validation', 'mandiri');
        Queue::assertPushed(SendReportCompletedNotification::class, 1);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'terbitkan_laporan_mandiri', 'actor_user_id' => $grafolog->id]);

        // Tidak muncul di tab "disetujui" supervisor - bukan hasil validasi.
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->actingAs($supervisor, 'sanctum')->getJson('/api/reviews?status=disetujui')->assertJsonCount(0, 'data');

        // Koreksi laporan terbit mandiri -> kembali draf, grafolog memutuskan ulang.
        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'ubah'])->assertOk();
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->fresh()->review_status);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
    }

    public function test_paid_validation_transfers_tokens_to_the_reviewing_supervisor_once(): void
    {
        $this->setFee(3);
        [$report, $grafolog, $client] = $this->draft(balance: 5);
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/report-validation/fee')->assertOk()
            ->assertJsonPath('tokens', 3)->assertJsonPath('balance', 5);
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/request-validation")
            ->assertOk()->assertJsonPath('review_status', 'menunggu_review')->assertJsonPath('validation_fee', 3);
        $this->assertSame(2, $grafolog->fresh()->token_balance);
        $this->assertDatabaseHas('token_ledger_entries', ['user_id' => $grafolog->id, 'type' => 'biaya_validasi', 'delta' => -3, 'report_id' => $report->id]);

        // Setelah diajukan tidak bisa diterbitkan sendiri / diajukan ulang.
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/publish")->assertUnprocessable();
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/request-validation")->assertUnprocessable();

        $this->actingAs($supervisor, 'sanctum')->getJson('/api/reviews')->assertJsonCount(1, 'data');
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/reports/{$report->id}/review", [
            'decision' => 'request_revision', 'note' => 'Cek aspek 01.',
        ])->assertOk();
        $this->assertSame(3, $supervisor->fresh()->token_balance);

        // Revisi grafolog -> kembali ke antrean supervisor, tanpa biaya baru; persetujuan tidak membayar dua kali.
        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'perbaikan'])->assertOk();
        $this->assertSame(PersonalityReport::REVIEW_PENDING, $report->fresh()->review_status);
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/reports/{$report->id}/review", ['decision' => 'approve'])->assertOk();

        $this->assertSame(3, $supervisor->fresh()->token_balance);
        $this->assertSame(2, $grafolog->fresh()->token_balance);
        $this->assertSame(1, TokenLedgerEntry::where('type', 'pendapatan_validasi')->count());
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('validation', 'supervisor');

        $this->actingAs($supervisor->fresh(), 'sanctum')->getJson('/api/tokens/wallet')->assertOk()
            ->assertJsonPath('balance', 3)->assertJsonPath('transactions.0.type', 'pendapatan_validasi');
    }

    public function test_insufficient_tokens_keep_report_as_draft(): void
    {
        $this->setFee(4);
        [$report, $grafolog] = $this->draft(balance: 1);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/request-validation")->assertStatus(402);
        $fresh = $report->fresh();
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $fresh->review_status);
        $this->assertNull($fresh->validation_requested_at);
        $this->assertSame(1, $grafolog->fresh()->token_balance);
    }

    public function test_free_validation_when_no_fee_configured(): void
    {
        [$report, $grafolog] = $this->draft();
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/request-validation")->assertOk();
        $this->actingAs($supervisor, 'sanctum')->postJson("/api/reports/{$report->id}/review", ['decision' => 'approve'])->assertOk();
        $this->assertSame(0, $supervisor->fresh()->token_balance);
        $this->assertNull($report->fresh()->validation_paid_at);
    }

    public function test_only_handling_grafolog_can_publish_or_request(): void
    {
        [$report] = $this->draft();
        $other = User::factory()->create(['role' => 'grafolog']);
        $client = $report->sample->user;

        $this->actingAs($other, 'sanctum')->postJson("/api/reports/{$report->id}/publish")->assertForbidden();
        $this->actingAs($other, 'sanctum')->postJson("/api/reports/{$report->id}/request-validation")->assertForbidden();
        $this->actingAs($client, 'sanctum')->postJson("/api/reports/{$report->id}/publish")->assertForbidden();
    }

    public function test_admin_sets_validation_fee(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/token-costs/validasi_supervisor', ['tokens_required' => 2])->assertCreated();
        $this->assertSame(2, TokenCost::activeTokensFor('validasi_supervisor'));
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => 2])->assertNotFound();
    }
}
