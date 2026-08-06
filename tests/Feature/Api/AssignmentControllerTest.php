<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function hrSample(): array
    {
        $company = Company::create(['name' => 'PT Uji Assignment']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $candidate = User::factory()->create(['role' => 'user', 'company_id' => $company->id]);
        $project = Project::create(['source' => 'hr', 'created_by' => $hr->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $candidate->id, 'created_by' => $hr->id,
            'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        return [$hr, $sample];
    }

    public function test_guest_cannot_assign(): void
    {
        [, $sample] = $this->hrSample();

        $this->postJson("/api/samples/{$sample->id}/assignment", [])->assertUnauthorized();
    }

    public function test_client_role_cannot_assign(): void
    {
        [, $sample] = $this->hrSample();
        $client = User::factory()->create(['role' => 'user']);

        $this->actingAs($client, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [])
            ->assertForbidden();
    }

    public function test_hr_can_assign_own_sample_to_a_grafolog(): void
    {
        [$hr, $sample] = $this->hrSample();
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($hr, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [
            'grafolog_id' => $grafolog->id,
        ]);

        $response->assertCreated()->assertJsonPath('status', 'assigned');
        $this->assertDatabaseHas('assignments', [
            'sample_id' => $sample->id, 'grafolog_id' => $grafolog->id, 'assigned_by' => $hr->id,
        ]);
    }

    public function test_hr_cannot_assign_sample_belonging_to_another_hr(): void
    {
        [, $sample] = $this->hrSample();
        $otherCompany = Company::create(['name' => 'PT Lain Assignment']);
        $otherHr = User::factory()->create(['role' => 'hr', 'company_id' => $otherCompany->id]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($otherHr, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [
            'grafolog_id' => $grafolog->id,
        ])->assertForbidden();
    }

    public function test_administrator_can_assign_any_sample(): void
    {
        [, $sample] = $this->hrSample();
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [
            'grafolog_id' => $grafolog->id,
        ])->assertCreated();
    }

    public function test_target_must_be_a_grafolog(): void
    {
        [$hr, $sample] = $this->hrSample();
        $notGrafolog = User::factory()->create(['role' => 'user']);

        $this->actingAs($hr, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [
            'grafolog_id' => $notGrafolog->id,
        ])->assertStatus(422);
    }

    public function test_reassigning_updates_existing_assignment_instead_of_duplicating(): void
    {
        [$hr, $sample] = $this->hrSample();
        $grafologA = User::factory()->create(['role' => 'grafolog']);
        $grafologB = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($hr, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [
            'grafolog_id' => $grafologA->id,
        ])->assertCreated();
        $this->actingAs($hr, 'sanctum')->postJson("/api/samples/{$sample->id}/assignment", [
            'grafolog_id' => $grafologB->id,
        ])->assertCreated();

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseHas('assignments', ['sample_id' => $sample->id, 'grafolog_id' => $grafologB->id]);
    }
}
