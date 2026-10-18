<?php

namespace Tests\Feature\Api;

use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $creator, string $source): Project
    {
        return Project::create(['source' => $source, 'created_by' => $creator->id]);
    }

    private function makeSample(Project $project, User $client, User $creator, string $status): HandwritingSample
    {
        return HandwritingSample::create([
            'project_id' => $project->id,
            'user_id' => $client->id,
            'created_by' => $creator->id,
            'tier' => 'comprehensive',
            'status' => $status,
        ]);
    }

    private function completeReport(HandwritingSample $sample, DateTimeInterface $generatedAt): PersonalityReport
    {
        return PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => $sample->tier,
            'status' => 'completed',
            'generated_at' => $generatedAt,
            'review_status' => PersonalityReport::REVIEW_APPROVED,
            'reviewed_at' => $generatedAt,
            'data' => ['sindrom' => []],
        ]);
    }

    public function test_guest_cannot_view_dashboard(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_grafolog_dashboard_reflects_own_data(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();

        // Sample masih pending -> ikut active_projects & pending_review.
        $pendingProject = $this->makeProject($grafolog, 'grafolog');
        $this->makeSample($pendingProject, $client, $grafolog, 'pending');

        // Sample selesai bulan ini, dibuat 2 hari lalu -> completed_this_month + avg_turnaround.
        $doneProject = $this->makeProject($grafolog, 'grafolog');
        $doneSample = $this->makeSample($doneProject, $client, $grafolog, 'completed');
        // created_at isn't fillable, so set + save directly rather than update().
        $doneSample->created_at = now()->subDays(2);
        $doneSample->save();
        $this->completeReport($doneSample, now());

        $response = $this->actingAs($grafolog, 'sanctum')->getJson('/api/dashboard');

        $response->assertOk()->assertJsonPath('role', 'grafolog');
        $kpi = collect($response->json('kpi'))->keyBy('key');

        $this->assertSame(1, $kpi['active_projects']['value']);
        $this->assertSame(1, $kpi['pending_review']['value']);
        $this->assertSame(1, $kpi['completed_this_month']['value']);
        $this->assertEqualsWithDelta(2.0, $kpi['avg_turnaround_days']['value'], 0.1);
    }

    public function test_client_dashboard_scopes_to_own_samples_only(): void
    {
        $client = User::factory()->create();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $stranger = User::factory()->create();

        $project = $this->makeProject($grafolog, 'grafolog');
        $sample = $this->makeSample($project, $client, $grafolog, 'completed');
        $sample->created_at = now()->subDay();
        $sample->save();
        $this->completeReport($sample, now());

        // Data milik user lain, tidak boleh bocor ke KPI client ini.
        $strangerProject = $this->makeProject($stranger, 'client');
        $this->makeSample($strangerProject, $stranger, $stranger, 'pending');

        $response = $this->actingAs($client, 'sanctum')->getJson('/api/dashboard');

        $response->assertOk()->assertJsonPath('role', 'client');
        $kpi = collect($response->json('kpi'))->keyBy('key');

        $this->assertSame(1, $kpi['total_assessments']['value']);
        $this->assertSame(1, $kpi['completed']['value']);
        $this->assertSame(0, $kpi['in_progress']['value']);
    }

    public function test_activity_feed_lists_recent_completed_reports(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $client = User::factory()->create();
        $project = $this->makeProject($grafolog, 'grafolog');
        $sample = $this->makeSample($project, $client, $grafolog, 'completed');
        $this->completeReport($sample, now());

        $response = $this->actingAs($grafolog, 'sanctum')->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonCount(1, 'activity')
            ->assertJsonPath('activity.0.status', 'completed');
    }

    public function test_client_does_not_count_report_still_in_review_as_completed(): void
    {
        $client = User::factory()->create();
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->makeSample($this->makeProject($grafolog, 'grafolog'), $client, $grafolog, 'completed');
        $this->completeReport($sample, now())->update(['review_status' => PersonalityReport::REVIEW_PENDING]);

        $kpi = collect($this->actingAs($client, 'sanctum')->getJson('/api/dashboard')->json('kpi'))->keyBy('key');

        $this->assertSame(0, $kpi['completed']['value']);
        $this->assertSame(1, $kpi['in_progress']['value']);
    }

    public function test_grafolog_dashboard_counts_assigned_samples(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $hr = User::factory()->create(['role' => 'hr']);
        $sample = $this->makeSample($this->makeProject($hr, 'hr'), User::factory()->create(), $hr, 'pending');
        $sample->assignment()->create(['grafolog_id' => $grafolog->id, 'assigned_by' => $hr->id, 'status' => 'assigned']);

        $kpi = collect($this->actingAs($grafolog, 'sanctum')->getJson('/api/dashboard')->json('kpi'))->keyBy('key');

        $this->assertSame(1, $kpi['pending_review']['value']);
    }

    public function test_admin_and_hr_get_their_own_dashboards(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->makeSample($this->makeProject($grafolog, 'grafolog'), User::factory()->create(), $grafolog, 'completed');
        $this->completeReport($sample, now())->update(['review_status' => PersonalityReport::REVIEW_PENDING]);

        $admin = collect($this->actingAs(User::factory()->create(['role' => 'administrator']), 'sanctum')
            ->getJson('/api/dashboard')->assertJsonPath('role', 'administrator')->json('kpi'))->keyBy('key');
        // KPI supervisi berjalan menghitung penugasan aktif (bukan laporan draf/pending lama).
        $this->assertSame(0, $admin['reviews_pending']['value']);

        $this->actingAs(User::factory()->create(['role' => 'hr']), 'sanctum')
            ->getJson('/api/dashboard')->assertJsonPath('role', 'hr')->assertJsonPath('kpi.0.value', 0);
    }
}
