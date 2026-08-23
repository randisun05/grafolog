<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_or_create_companies(): void
    {
        $this->getJson('/api/admin/companies')->assertUnauthorized();
        $this->postJson('/api/admin/companies', [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->postJson('/api/admin/companies', ['name' => 'PT Contoh'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_and_list_companies(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'PT Nusantara Rekrut',
        ]);
        $create->assertCreated()->assertJsonPath('name', 'PT Nusantara Rekrut');

        $list = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/companies');
        $list->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_duplicate_company_name_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', ['name' => 'PT Dup'])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', ['name' => 'PT Dup'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_administrator_can_update_company_name_and_active_state(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Lama']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/companies/{$company->id}", [
            'name' => 'PT Baru', 'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('name', 'PT Baru')->assertJsonPath('is_active', false);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_perusahaan', 'target_id' => $company->id]);
    }

    public function test_updating_company_keeps_hr_accounts_active(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Nonaktif']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);

        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/companies/{$company->id}", [
            'name' => $company->name, 'is_active' => false,
        ])->assertOk();

        $this->assertTrue($hr->fresh()->is_active);
    }

    // --- Fase 1: dashboard admin lintas-perusahaan ---

    public function test_index_includes_aggregate_stats_for_company_with_candidates(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Rekrut Aktif']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $project = Project::create(['source' => 'hr', 'created_by' => $hr->id]);

        $doneSample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hr->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);
        $doneSample->created_at = now()->subDays(2);
        $doneSample->save();
        PersonalityReport::create([
            'sample_id' => $doneSample->id, 'tier' => 'comprehensive', 'status' => 'completed',
            'generated_at' => now(), 'data' => ['sindrom' => []],
        ]);

        HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hr->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/companies');

        $response->assertOk()
            ->assertJsonPath('data.0.hr_count', 1)
            ->assertJsonPath('data.0.total_candidates', 2)
            ->assertJsonPath('data.0.completed_reports', 1);
        $this->assertEqualsWithDelta(2.0, $response->json('data.0.avg_turnaround_days'), 0.1);
    }

    public function test_index_stats_are_zero_for_company_with_no_hr_or_candidates(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        Company::create(['name' => 'PT Kosong']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/companies');

        $response->assertOk()
            ->assertJsonPath('data.0.hr_count', 0)
            ->assertJsonPath('data.0.total_candidates', 0)
            ->assertJsonPath('data.0.completed_reports', 0)
            ->assertJsonPath('data.0.avg_turnaround_days', null);
    }

    public function test_index_stats_do_not_leak_across_companies(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $companyA = Company::create(['name' => 'PT A']);
        $companyB = Company::create(['name' => 'PT B']);
        $hrA = User::factory()->create(['role' => 'hr', 'company_id' => $companyA->id]);
        $hrB = User::factory()->create(['role' => 'hr', 'company_id' => $companyB->id]);
        $projectA = Project::create(['source' => 'hr', 'created_by' => $hrA->id]);
        $projectB = Project::create(['source' => 'hr', 'created_by' => $hrB->id]);

        HandwritingSample::create([
            'project_id' => $projectA->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hrA->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        HandwritingSample::create([
            'project_id' => $projectB->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hrB->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);
        HandwritingSample::create([
            'project_id' => $projectB->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hrB->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/companies');

        $byName = collect($response->json('data'))->keyBy('name');
        $this->assertSame(1, $byName['PT A']['total_candidates']);
        $this->assertSame(2, $byName['PT B']['total_candidates']);
    }
}
