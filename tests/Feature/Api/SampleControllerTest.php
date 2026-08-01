<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class SampleControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    public function test_guest_cannot_list_samples(): void
    {
        $this->getJson('/api/samples')->assertUnauthorized();
    }

    public function test_rapid_tier_is_retired_and_rejected_on_create(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/samples', [
            'tier' => 'rapid',
            'image' => UploadedFile::fake()->image('tulisan.jpg'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('tier');
        $this->assertDatabaseCount('handwriting_samples', 0);
    }

    public function test_old_rapid_sample_stays_viewable_after_retirement(): void
    {
        $owner = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'tier' => 'rapid',
            'status' => 'completed',
            'image_path' => 'handwriting-samples/old-sample.jpg',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/samples/{$sample->id}")
            ->assertOk()
            ->assertJsonPath('tier', 'rapid');
    }

    public function test_comprehensive_upload_rejects_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/samples', [
            'tier' => 'comprehensive',
            'image' => UploadedFile::fake()->image('tulisan.jpg'),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_regular_user_cannot_set_client_user_id(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/samples', [
            'tier' => 'comprehensive',
            'client_user_id' => $other->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('client_user_id');
    }

    public function test_grafolog_must_provide_client_user_id(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson('/api/samples', [
            'tier' => 'comprehensive',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('client_user_id');
    }

    public function test_grafolog_can_create_comprehensive_sample_for_client(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson('/api/samples', [
            'tier' => 'comprehensive',
            'client_user_id' => $client->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('handwriting_samples', [
            'user_id' => $client->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('personality_reports', 0);
    }

    public function test_creating_a_sample_creates_a_wrapping_project(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson('/api/samples', [
            'tier' => 'comprehensive',
            'client_user_id' => $client->id,
        ]);

        $sampleId = $response->json('id');
        $sample = HandwritingSample::find($sampleId);

        $this->assertNotNull($sample->project_id);
        $this->assertDatabaseHas('projects', [
            'id' => $sample->project_id,
            'source' => 'grafolog',
            'created_by' => $grafolog->id,
        ]);
    }

    public function test_self_service_client_project_source_is_client(): void
    {
        $client = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/samples', [
            'tier' => 'comprehensive',
        ]);

        $sample = HandwritingSample::find($response->json('id'));

        $this->assertDatabaseHas('projects', [
            'id' => $sample->project_id,
            'source' => 'client',
            'created_by' => $client->id,
        ]);
    }

    public function test_user_cannot_view_sample_belonging_to_someone_else(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/samples/{$sample->id}")
            ->assertForbidden();
    }

    public function test_owner_can_view_own_sample(): void
    {
        $owner = User::factory()->create();
        $sample = HandwritingSample::create([
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/samples/{$sample->id}")
            ->assertOk()
            ->assertJsonPath('id', $sample->id);
    }

    public function test_index_only_lists_samples_owned_or_created_by_user(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        HandwritingSample::create([
            'user_id' => $user->id, 'created_by' => $user->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        HandwritingSample::create([
            'user_id' => $stranger->id, 'created_by' => $stranger->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/samples');

        $response->assertOk()->assertJsonCount(1, 'data');
    }
}
