<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
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
}
