<?php

namespace Tests\Feature\Api;

use App\Jobs\SendReportCompletedNotification;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class ReportReviewTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function pendingReport(): array
    {
        $sindrom = $this->seedMinimalAspek(1);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $client->id, 'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        $report = PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => ['sindrom' => [[
                'id' => $sindrom->id, 'kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas' => 'HIJAU',
                'aspek' => [['kode' => '01', 'nama' => 'Aspek 1', 'skor' => 5, 'narasi' => 'asli']],
            ]]],
        ]);

        return [$report, $grafolog, $client];
    }

    public function test_new_report_waits_for_review_and_is_hidden_from_client(): void
    {
        [$report, $grafolog, $client] = $this->pendingReport();
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->assertSame(PersonalityReport::REVIEW_PENDING, $report->fresh()->review_status);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
        $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk();
        $this->actingAs($supervisor, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk();

        $ids = array_column($this->actingAs($supervisor, 'sanctum')->getJson('/api/reviews')->json('data'), 'id');
        $this->assertSame([$report->id], $ids);
    }

    public function test_only_supervisor_or_admin_can_review(): void
    {
        [$report, $grafolog, $client] = $this->pendingReport();

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/reports/{$report->id}/review", ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($client, 'sanctum')->getJson('/api/reviews')->assertForbidden();
    }

    public function test_approval_releases_report_and_sends_email(): void
    {
        Queue::fake();
        [$report, , $client] = $this->pendingReport();
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/reports/{$report->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('review_status', 'disetujui')
            ->assertJsonPath('reviewer.id', $supervisor->id);

        Queue::assertPushed(SendReportCompletedNotification::class, 1);
        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'review_laporan_disetujui', 'actor_user_id' => $supervisor->id]);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/reports/{$report->id}/review", ['decision' => 'approve'])->assertUnprocessable();
    }

    public function test_revision_request_needs_note_and_grafolog_edit_requeues(): void
    {
        Queue::fake();
        [$report, $grafolog] = $this->pendingReport();
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/reports/{$report->id}/review", ['decision' => 'request_revision'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/reports/{$report->id}/review", ['decision' => 'request_revision', 'note' => 'Narasi aspek 01 kurang tepat.'])
            ->assertOk()->assertJsonPath('review_status', 'perlu_revisi');

        $this->actingAs($grafolog, 'sanctum')->getJson("/api/reports/{$report->id}")
            ->assertJsonPath('review_note', 'Narasi aspek 01 kurang tepat.');

        $this->actingAs($grafolog, 'sanctum')
            ->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'diperbaiki'])->assertOk();

        $this->assertSame(PersonalityReport::REVIEW_PENDING, $report->fresh()->review_status);
        Queue::assertNotPushed(SendReportCompletedNotification::class);
    }

    public function test_editing_an_approved_report_sends_it_back_to_review(): void
    {
        Queue::fake();
        [$report, $grafolog, $client] = $this->pendingReport();
        $report->update(['review_status' => PersonalityReport::REVIEW_APPROVED]);

        $this->actingAs($grafolog, 'sanctum')
            ->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'ubah setelah disetujui'])->assertOk();

        $this->actingAs($client, 'sanctum')->getJson("/api/reports/{$report->id}")->assertForbidden();
    }
}
