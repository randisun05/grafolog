<?php

namespace Tests\Feature\Api;

use App\Models\Aspek;
use App\Models\Assignment;
use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\Payment;
use App\Models\Project;
use App\Models\SampleIndikatorCheck;
use App\Models\TokenCost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class ScoringControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function skorPayload(int $count): array
    {
        $skor = [];
        for ($i = 1; $i <= $count; $i++) {
            $skor[] = ['kode' => str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'skor' => 7];
        }

        return ['skor' => $skor];
    }

    public function test_grafolog_can_submit_full_scores_for_own_sample(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $client->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated()
            ->assertJsonPath('status', 'completed')
            ->assertJsonCount(3, 'aspek_scores');

        $this->assertDatabaseHas('personality_reports', ['sample_id' => $sample->id, 'status' => 'completed']);
        $this->assertDatabaseHas('handwriting_samples', ['id' => $sample->id, 'status' => 'completed']);
        $this->assertDatabaseCount('report_aspek_scores', 3);
    }

    public function test_submit_attaches_checked_indikator_narasi_to_matching_aspek(): void
    {
        $this->seedMinimalAspek(3);
        $aspek01 = Aspek::where('kode', '01')->firstOrFail();
        $indikator = Indikator::create([
            'kode' => '01-1', 'posisi' => 1, 'aspek_id' => $aspek01->id,
            'nama' => 'Angles in the middle zone', 'keterangan' => 'Penjelasan indikator 01-1.',
        ]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);
        SampleIndikatorCheck::create([
            'sample_id' => $sample->id, 'indikator_id' => $indikator->id,
            'checked' => true, 'sumber' => 'manual',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated();
        $aspekData = collect($response->json('data.sindrom'))->pluck('aspek')->flatten(1)->firstWhere('kode', '01');
        $this->assertSame([
            ['kode' => '01-1', 'nama' => 'Angles in the middle zone', 'keterangan' => 'Penjelasan indikator 01-1.'],
        ], $aspekData['indikator_terkait']);

        $aspekWithoutChecks = collect($response->json('data.sindrom'))->pluck('aspek')->flatten(1)->firstWhere('kode', '02');
        $this->assertArrayNotHasKey('indikator_terkait', $aspekWithoutChecks);
    }

    public function test_incomplete_scores_are_rejected(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(2));

        $response->assertUnprocessable()->assertJsonValidationErrors('skor');
        $this->assertDatabaseCount('personality_reports', 0);
    }

    public function test_non_grafolog_cannot_submit_scores(): void
    {
        $this->seedMinimalAspek(3);
        $user = User::factory()->create(['role' => 'user']);
        $sample = HandwritingSample::create([
            'user_id' => $user->id,
            'created_by' => $user->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertForbidden();
    }

    public function test_grafolog_cannot_submit_scores_for_sample_they_did_not_create(): void
    {
        $this->seedMinimalAspek(3);
        $grafologA = User::factory()->create(['role' => 'grafolog']);
        $grafologB = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafologA->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafologB, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertForbidden();
    }

    public function test_rapid_tier_sample_rejects_manual_scoring(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => $grafolog->id,
            'created_by' => $grafolog->id,
            'tier' => 'rapid',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertStatus(422);
    }

    public function test_already_completed_sample_rejects_resubmission(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $first = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));
        $first->assertCreated();

        $second = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $second->assertStatus(422);
        $this->assertDatabaseCount('personality_reports', 1);
    }

    public function test_score_out_of_range_is_rejected(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $payload = $this->skorPayload(3);
        $payload['skor'][0]['skor'] = 11;

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $payload);

        $response->assertUnprocessable()->assertJsonValidationErrors('skor.0.skor');
    }

    public function test_preview_accepts_partial_scores_and_persists_nothing(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        // Baru 1 dari 3 aspek terisi - submit() akan menolak ini, preview() tidak.
        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/preview", $this->skorPayload(1));

        $response->assertOk()->assertJsonCount(1, 'sindrom.0.aspek');
        $this->assertDatabaseCount('personality_reports', 0);
        $this->assertDatabaseCount('report_aspek_scores', 0);
        $this->assertDatabaseHas('handwriting_samples', ['id' => $sample->id, 'status' => 'pending']);
    }

    public function test_preview_with_empty_scores_returns_empty_result(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/preview", ['skor' => []]);

        $response->assertOk()->assertJsonPath('sindrom', []);
    }

    public function test_assigned_grafolog_who_is_not_the_creator_can_submit_scores(): void
    {
        // MGA Fase 06: HR creates the sample (created_by = HR), a different
        // grafolog is explicitly assigned - that grafolog must still be
        // able to score it even though isScorableBy()'s original half
        // (created_by === user.id) is false for them.
        $this->seedMinimalAspek(3);
        $hr = User::factory()->create(['role' => 'hr']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $hr->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);
        Assignment::create([
            'sample_id' => $sample->id, 'grafolog_id' => $grafolog->id,
            'assigned_by' => $hr->id, 'status' => 'assigned',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated();
        $this->assertDatabaseHas('assignments', ['sample_id' => $sample->id, 'status' => 'completed']);
    }

    public function test_preview_forbidden_for_grafolog_who_did_not_create_sample(): void
    {
        $this->seedMinimalAspek(3);
        $grafologA = User::factory()->create(['role' => 'grafolog']);
        $grafologB = User::factory()->create(['role' => 'grafolog']);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafologA->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafologB, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/preview", $this->skorPayload(1));

        $response->assertForbidden();
    }

    public function test_unpaid_client_sourced_sample_blocks_scoring(): void
    {
        // Commerce inisiatif: Project.source === 'client' wajib lunas dulu.
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $project = Project::create(['source' => 'client', 'created_by' => $client->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id,
            'user_id' => $client->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $preview = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores/preview", $this->skorPayload(1));
        $preview->assertStatus(402);

        $submit = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));
        $submit->assertStatus(402);
        $this->assertDatabaseCount('personality_reports', 0);
    }

    public function test_paid_client_sourced_sample_allows_scoring(): void
    {
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $project = Project::create(['source' => 'client', 'created_by' => $client->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id,
            'user_id' => $client->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);
        Payment::create([
            'sample_id' => $sample->id, 'invoice_number' => 'INV-PAID-1',
            'amount' => 49000, 'status' => 'paid',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated();
    }

    public function test_grafolog_and_hr_sourced_samples_are_not_payment_gated(): void
    {
        // Sample tanpa project (pola paling umum di test lain di file ini)
        // dan sample dengan project source hr/grafolog harus tetap bisa
        // discor tanpa Payment sama sekali - gate cuma untuk source client.
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $hrProject = Project::create(['source' => 'hr', 'created_by' => $grafolog->id]);
        $sample = HandwritingSample::create([
            'project_id' => $hrProject->id,
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated();
    }

    public function test_no_token_gate_when_tier_not_configured(): void
    {
        // token_costs kosong sama sekali (default rilis) - TokenCost::activeTokensFor()
        // mengembalikan null, diperlakukan sebagai 0, tidak ada gate sama sekali.
        // Ini yang menjaga rilis fitur token TIDAK langsung memblokir grafolog lama.
        $this->seedMinimalAspek(3);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 0]);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated();
    }

    public function test_token_gate_blocks_submission_when_balance_insufficient(): void
    {
        $this->seedMinimalAspek(3);
        TokenCost::create(['tier' => 'comprehensive', 'tokens_required' => 2, 'is_active' => true]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 1]);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertStatus(402);
        $this->assertDatabaseCount('personality_reports', 0);
        $this->assertSame(1, $grafolog->fresh()->token_balance);
    }

    public function test_token_gate_allows_submission_and_deducts_balance_when_sufficient(): void
    {
        $this->seedMinimalAspek(3);
        TokenCost::create(['tier' => 'comprehensive', 'tokens_required' => 2, 'is_active' => true]);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'token_balance' => 5]);
        $sample = HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/samples/{$sample->id}/scores", $this->skorPayload(3));

        $response->assertCreated();
        $this->assertSame(3, $grafolog->fresh()->token_balance);
        $this->assertDatabaseHas('token_ledger_entries', [
            'user_id' => $grafolog->id, 'type' => 'consumption', 'delta' => -2, 'balance_after' => 3,
        ]);
    }
}
