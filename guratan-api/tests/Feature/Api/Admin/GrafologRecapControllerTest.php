<?php

namespace Tests\Feature\Api\Admin;

use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrafologRecapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/admin/recap/grafolog')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/recap/grafolog')->assertForbidden();
    }

    public function test_lists_only_grafolog_role_with_stats(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'name' => 'Grafolog Satu']);
        User::factory()->create(['role' => 'user']);

        $project = Project::create(['source' => 'grafolog', 'created_by' => $grafolog->id]);
        $doneSample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        $doneSample->created_at = now()->subDays(3);
        $doneSample->save();
        PersonalityReport::create([
            'sample_id' => $doneSample->id, 'tier' => 'comprehensive', 'status' => 'completed',
            'generated_at' => now(), 'data' => ['sindrom' => []],
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/grafolog');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $grafolog->id)
            ->assertJsonPath('data.0.completed_reports', 1);
        $this->assertEqualsWithDelta(3.0, $response->json('data.0.avg_turnaround_days'), 0.1);
    }

    public function test_stats_are_zero_for_grafolog_with_no_samples(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/grafolog');

        $response->assertOk()
            ->assertJsonPath('data.0.completed_reports', 0)
            ->assertJsonPath('data.0.avg_turnaround_days', null);
    }

    public function test_export_returns_csv_and_logs_audit(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'grafolog', 'name' => 'Grafolog CSV', 'email' => 'grafolog-csv@example.com']);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/admin/recap/grafolog/export');

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Grafolog CSV', $content);
        $this->assertStringContainsString('grafolog-csv@example.com', $content);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ekspor_rekap_grafolog', 'actor_user_id' => $admin->id]);
    }
}
