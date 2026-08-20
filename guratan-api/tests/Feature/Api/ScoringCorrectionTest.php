<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

/**
 * ScoringController::correct() - kebalikan dari submit(): submit() menolak
 * sample yang sudah completed, correct() JUSTRU mensyaratkan itu. Lihat
 * CLAUDE.md untuk alasan desain (report_revisions sebagai jejak audit).
 */
class ScoringCorrectionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function completedSample(User $grafolog, int $aspekCount = 3): HandwritingSample
    {
        $this->seedMinimalAspek($aspekCount);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);
        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload($aspekCount, 5))
            ->assertCreated();

        return $sample->fresh();
    }

    private function skorPayload(int $count, int $skor): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['kode' => str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'skor' => $skor];
        }

        return ['skor' => $rows];
    }

    public function test_owner_grafolog_can_correct_scores_of_completed_sample(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->completedSample($grafolog);
        $report = $sample->reports()->first();

        $response = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores/correct", [
            ...$this->skorPayload(3, 9),
            'catatan' => 'Salah input skor Aspek 02 sebelumnya.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('report_aspek_scores', ['report_id' => $report->id, 'skor' => 9]);
        $this->assertDatabaseCount('report_aspek_scores', 3);
        $this->assertSame(9, $report->fresh()->data['sindrom'][0]['aspek'][0]['skor']);
    }

    public function test_correction_snapshots_previous_version_to_revisions(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->completedSample($grafolog);
        $report = $sample->reports()->first();
        $originalData = $report->data;

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/scores/correct", [
            ...$this->skorPayload(3, 9),
            'catatan' => 'koreksi',
        ])->assertOk();

        $this->assertDatabaseCount('report_revisions', 1);
        $revision = $report->revisions()->first();
        $this->assertSame('koreksi_skor', $revision->jenis);
        $this->assertSame('koreksi', $revision->catatan);
        $this->assertSame($grafolog->id, $revision->actor_user_id);
        $this->assertSame($originalData['sindrom'][0]['aspek'][0]['skor'], $revision->data['sindrom'][0]['aspek'][0]['skor']);

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'koreksi_skor_laporan', 'target_id' => $report->id, 'actor_user_id' => $grafolog->id,
        ]);
    }

    public function test_correction_rejected_for_sample_without_completed_report(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/correct", $this->skorPayload(3, 5))
            ->assertStatus(422);
    }

    public function test_non_owner_grafolog_cannot_correct_scores(): void
    {
        $owner = User::factory()->create(['role' => 'grafolog']);
        $stranger = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->completedSample($owner);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/correct", $this->skorPayload(3, 5))
            ->assertForbidden();
    }

    public function test_correction_does_not_charge_tokens(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 5]);
        $sample = $this->completedSample($grafolog);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/correct", $this->skorPayload(3, 9))
            ->assertOk();

        $this->assertSame(5, $grafolog->fresh()->token_balance);
    }

    public function test_correcting_clears_manual_narasi_override_on_regeneration(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->completedSample($grafolog);
        $report = $sample->reports()->first();

        $this->actingAs($grafolog, 'sanctum')
            ->patchJson("/api/reports/{$report->id}/aspek/01/narasi", ['narasi' => 'Teks override manual.'])
            ->assertOk();
        $this->assertTrue($report->fresh()->data['sindrom'][0]['aspek'][0]['narasi_diedit_manual']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/correct", $this->skorPayload(3, 9))
            ->assertOk();

        $fresh = $report->fresh()->data['sindrom'][0]['aspek'][0];
        $this->assertArrayNotHasKey('narasi_diedit_manual', $fresh);
        $this->assertNotSame('Teks override manual.', $fresh['narasi']);
    }
}
