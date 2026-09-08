<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_user_ids_returns_only_hr_accounts_of_this_company(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $otherCompany = Company::create(['name' => 'PT Lain']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        User::factory()->create(['role' => 'hr', 'company_id' => $otherCompany->id]);

        $ids = $company->hrUserIds();

        $this->assertSame([$hr->id], $ids->all());
    }

    public function test_sample_ids_aggregates_across_all_hr_accounts_and_excludes_other_companies(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $hrOne = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $hrTwo = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);

        $projectOne = Project::create(['source' => 'hr', 'created_by' => $hrOne->id]);
        $sampleOne = HandwritingSample::create([
            'project_id' => $projectOne->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hrOne->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $projectTwo = Project::create(['source' => 'hr', 'created_by' => $hrTwo->id]);
        $sampleTwo = HandwritingSample::create([
            'project_id' => $projectTwo->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $hrTwo->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        // Sample dari grafolog-langsung, project.creator bukan HR company manapun.
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $strayProject = Project::create(['source' => 'grafolog', 'created_by' => $grafolog->id]);
        HandwritingSample::create([
            'project_id' => $strayProject->id, 'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id, 'tier' => 'comprehensive', 'status' => 'pending',
        ]);

        $ids = $company->sampleIds();

        $this->assertEqualsCanonicalizing([$sampleOne->id, $sampleTwo->id], $ids->all());
    }
}
