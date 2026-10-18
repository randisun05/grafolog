<?php

namespace Tests\Feature\Api;

use App\Jobs\SendReportCompletedNotification;
use App\Models\GrafologAvailability;
use App\Models\HandwritingSample;
use App\Models\InboxMessage;
use App\Models\PersonalityReport;
use App\Models\ReportValidation;
use App\Models\TokenCost;
use App\Models\User;
use App\Services\Reporting\ReportValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/** Supervisi antar-grafolog dengan escrow token & tenggat (2026-10-18). */
class ReportValidationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Mail::fake();
    }

    /** @return array{0: PersonalityReport, 1: User, 2: User} */
    private function draft(int $balance = 0): array
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified', 'token_balance' => $balance]);
        $client = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => 'master', 'status' => 'pending',
        ]);
        $skor = collect(range(1, 3))->map(fn ($i) => ['kode' => str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'skor' => 7])->all();
        $id = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores", ['skor' => $skor])
            ->assertCreated()->json('id');

        return [PersonalityReport::findOrFail($id), $grafolog, $client];
    }

    private function validator(bool $supervision = true): User
    {
        $v = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        if ($supervision) {
            GrafologAvailability::create(['user_id' => $v->id, 'month' => now()->format('Y-m'), 'status' => 'tersedia', 'supervision' => true]);
        }

        return $v;
    }

    private function setFee(int $tokens): void
    {
        TokenCost::setTokensFor('validasi_supervisor', $tokens, User::factory()->create(['role' => 'administrator']));
    }

    private function assign(PersonalityReport $r, User $by, User $validator, int $days = 5)
    {
        return $this->actingAs($by, 'sanctum')->postJson("/api/reports/{$r->id}/request-validation", [
            'validator_id' => $validator->id, 'deadline_days' => $days,
        ]);
    }

    public function test_new_report_is_a_draft_hidden_from_client(): void
    {
        [$report, $grafolog, $client] = $this->draft();

        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->review_status);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
        $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('review_status', 'draf');
        Queue::assertNotPushed(SendReportCompletedNotification::class);
    }

    public function test_grafolog_can_publish_without_validation_and_edit_returns_it_to_draft(): void
    {
        [$report, $grafolog, $client] = $this->draft();

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/publish")
            ->assertOk()->assertJsonPath('validation', 'mandiri');
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk();
        Queue::assertPushed(SendReportCompletedNotification::class, 1);

        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'ubah'])->assertOk();
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->fresh()->review_status);
    }

    public function test_only_validators_available_for_supervision_can_be_picked(): void
    {
        [$report, $grafolog] = $this->draft();
        $open = $this->validator();
        $closed = $this->validator(false);

        $ids = collect($this->actingAs($grafolog, 'sanctum')->getJson('/api/report-validation/validators')->assertOk()->json())->pluck('id');
        $this->assertEquals([$open->id], $ids->all());

        $this->assign($report, $grafolog, $closed)->assertUnprocessable();
        $this->assign($report, $grafolog, $grafolog)->assertUnprocessable();
        $this->assign($report, $grafolog, $open, 31)->assertUnprocessable(); // tenggat > 30 hari
        // Grafolog yang menangani sampel tidak boleh jadi validator laporannya sendiri.
        $assigned = $this->validator();
        $report->sample->assignment()->create(['grafolog_id' => $assigned->id, 'assigned_by' => $grafolog->id, 'status' => 'assigned']);
        $this->assign($report, $grafolog, $assigned)->assertUnprocessable();
    }

    public function test_escrow_tokens_release_to_validator_only_after_requester_confirms(): void
    {
        $this->setFee(3);
        [$report, $grafolog, $client] = $this->draft(balance: 5);
        $v = $this->validator();

        $this->assign($report, $grafolog, $v, 7)->assertCreated()->assertJsonPath('fee', 3);
        $this->assertSame(2, $grafolog->fresh()->token_balance);
        $this->assertSame(0, $v->fresh()->token_balance);
        $this->assertSame(PersonalityReport::REVIEW_PENDING, $report->fresh()->review_status);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $v->id, 'type' => 'supervisi_ditugaskan']);

        // Laporan terkunci selama supervisi; validator bisa membacanya; pihak lain tidak.
        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'x'])->assertUnprocessable();
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/publish")->assertUnprocessable();
        $this->actingAs($v, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk();
        $this->actingAs($this->validator(false), 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();

        $validation = ReportValidation::firstOrFail();
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/validations/{$validation->id}/decide", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($v, 'sanctum')->postJson("/api/validations/{$validation->id}/decide", ['decision' => 'approve', 'note' => 'Sesuai.'])
            ->assertOk()->assertJsonPath('status', 'disetujui');

        // Disetujui validator, tapi belum terbit & token belum cair sampai pembuat konfirmasi.
        $this->assertSame(0, $v->fresh()->token_balance);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
        $this->actingAs($v, 'sanctum')->postJson("/api/validations/{$validation->id}/confirm")->assertForbidden();

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/validations/{$validation->id}/confirm")->assertOk()->assertJsonPath('status', 'selesai');
        $this->assertSame(3, $v->fresh()->token_balance);
        $this->assertSame(2, $grafolog->fresh()->token_balance);
        $this->assertDatabaseHas('token_ledger_entries', ['user_id' => $v->id, 'type' => 'pendapatan_validasi', 'delta' => 3]);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('validation', 'supervisor');
        Queue::assertPushed(SendReportCompletedNotification::class, 1);
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/validations/{$validation->id}/confirm")->assertUnprocessable();
    }

    public function test_revision_needs_note_and_returns_report_to_creator(): void
    {
        $this->setFee(2);
        [$report, $grafolog] = $this->draft(balance: 4);
        $v = $this->validator();
        $this->assign($report, $grafolog, $v)->assertCreated();
        $validation = ReportValidation::firstOrFail();

        $this->actingAs($v, 'sanctum')->postJson("/api/validations/{$validation->id}/decide", ['decision' => 'request_revision'])->assertUnprocessable();
        $this->actingAs($v, 'sanctum')->postJson("/api/validations/{$validation->id}/decide", ['decision' => 'request_revision', 'note' => 'Cek aspek 01.'])->assertOk();
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/validations/{$validation->id}/confirm")->assertOk();

        $fresh = $report->fresh();
        $this->assertSame(PersonalityReport::REVIEW_REVISION, $fresh->review_status);
        $this->assertSame('Cek aspek 01.', $fresh->review_note);
        $this->assertSame(2, $v->fresh()->token_balance); // validator tetap dibayar atas kerjanya

        // Setelah revisi, pembuat bebas mengedit lalu menugaskan ulang (biaya baru) atau menerbitkan.
        $this->actingAs($grafolog, 'sanctum')->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'diperbaiki'])->assertOk();
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->fresh()->review_status);
        $this->assign($report, $grafolog, $this->validator())->assertCreated();
        $this->assertSame(0, $grafolog->fresh()->token_balance);
    }

    public function test_declined_or_expired_assignment_refunds_tokens_and_allows_reassignment(): void
    {
        $this->setFee(3);
        [$report, $grafolog] = $this->draft(balance: 3);
        $v1 = $this->validator();
        $this->assign($report, $grafolog, $v1)->assertCreated();
        $this->assertSame(0, $grafolog->fresh()->token_balance);

        $first = ReportValidation::firstOrFail();
        $this->actingAs($v1, 'sanctum')->postJson("/api/validations/{$first->id}/decline", ['note' => 'Sedang penuh'])->assertOk()->assertJsonPath('status', 'ditolak');
        $this->assertSame(3, $grafolog->fresh()->token_balance);
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->fresh()->review_status);

        $v2 = $this->validator();
        $this->assign($report, $grafolog, $v2, 2)->assertCreated();
        $this->assertSame(0, $grafolog->fresh()->token_balance);

        $this->travel(3)->days();
        $this->actingAs($v2, 'sanctum')->postJson('/api/validations/'.ReportValidation::latest('id')->value('id').'/decide', ['decision' => 'approve'])->assertUnprocessable();
        $this->assertSame(1, app(ReportValidationService::class)->sweep());
        $this->assertSame(3, $grafolog->fresh()->token_balance);
        $this->assertSame('kedaluwarsa', ReportValidation::latest('id')->first()->status);
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->fresh()->review_status);
        $this->assertDatabaseHas('inbox_messages', ['user_id' => $grafolog->id, 'type' => 'supervisi_kedaluwarsa']);

        $this->assign($report, $grafolog, $this->validator())->assertCreated(); // bisa ditugaskan ulang
    }

    public function test_unconfirmed_result_is_auto_confirmed_after_three_days(): void
    {
        $this->setFee(2);
        [$report, $grafolog, $client] = $this->draft(balance: 2);
        $v = $this->validator();
        $this->assign($report, $grafolog, $v)->assertCreated();
        $validation = ReportValidation::firstOrFail();
        $this->actingAs($v, 'sanctum')->postJson("/api/validations/{$validation->id}/decide", ['decision' => 'approve'])->assertOk();

        $this->travel(4)->days();
        app(ReportValidationService::class)->sweep();

        $this->assertSame('selesai', $validation->fresh()->status);
        $this->assertSame(2, $v->fresh()->token_balance);
        $this->assertSame(PersonalityReport::REVIEW_APPROVED, $report->fresh()->review_status);
    }

    public function test_insufficient_tokens_block_assignment(): void
    {
        $this->setFee(4);
        [$report, $grafolog] = $this->draft(balance: 1);

        $this->assign($report, $grafolog, $this->validator())->assertStatus(402);
        $this->assertSame(0, ReportValidation::count());
        $this->assertSame(PersonalityReport::REVIEW_DRAFT, $report->fresh()->review_status);
        $this->assertSame(1, $grafolog->fresh()->token_balance);
    }

    public function test_lists_and_admin_index_without_token_for_admin(): void
    {
        $this->setFee(2);
        [$report, $grafolog] = $this->draft(balance: 2);
        $v = $this->validator();
        $this->assign($report, $grafolog, $v)->assertCreated();
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($v, 'sanctum')->getJson('/api/validations')->assertOk()->assertJsonCount(1, 'assigned')->assertJsonCount(0, 'requested');
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/validations')->assertJsonCount(1, 'requested');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/validations')->assertOk()->assertJsonPath('data.0.validator.id', $v->id);
        $this->actingAs($admin, 'sanctum')->getJson("/api/reports/{$report->id}/validations")->assertOk()->assertJsonCount(1);
        $this->assertSame(0, $admin->fresh()->token_balance);
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/validations')->assertForbidden();
    }

    public function test_admin_sets_validation_fee(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/token-costs/validasi_supervisor', ['tokens_required' => 2])->assertCreated();
        $this->assertSame(2, TokenCost::activeTokensFor('validasi_supervisor'));
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/token-costs/comprehensive', ['tokens_required' => 2])->assertNotFound();
        $this->assertSame(0, InboxMessage::count());
    }
}
