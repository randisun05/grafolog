<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function completedReportFor(User $owner): PersonalityReport
    {
        $sindrom = $this->seedMinimalAspek(2);
        $sample = HandwritingSample::create([
            'user_id' => $owner->id,
            'created_by' => $owner->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
            'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $sindrom->id,
                    'kode_romawi' => $sindrom->kode_romawi,
                    'nama' => $sindrom->nama,
                    'polaritas' => 'HIJAU',
                    'catatan_polaritas' => null,
                    'rata_rata_skor' => 7.0,
                    'band_label_rata_rata' => 'Nilai Tinggi',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek 1', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'narasi high 1'],
                    ],
                ]],
            ],
        ]);
    }

    /**
     * Sample dibuat oleh $hr (bukan $candidate sendiri), dibungkus Project
     * source='hr' - rantai yang dipakai Company::sampleIds()/
     * HandwritingSample::isViewableBy() untuk otorisasi Supervisor.
     */
    private function completedReportForHrCandidate(User $hr, User $candidate): PersonalityReport
    {
        $sindrom = $this->seedMinimalAspek(1);
        $project = Project::create(['source' => 'hr', 'created_by' => $hr->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id,
            'user_id' => $candidate->id,
            'created_by' => $hr->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id,
            'tier' => 'comprehensive',
            'status' => 'completed',
            'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $sindrom->id,
                    'kode_romawi' => $sindrom->kode_romawi,
                    'nama' => $sindrom->nama,
                    'polaritas' => 'HIJAU',
                    'catatan_polaritas' => null,
                    'rata_rata_skor' => 7.0,
                    'band_label_rata_rata' => 'Nilai Tinggi',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek 1', 'skor' => 7, 'band_label' => 'Nilai Tinggi', 'narasi_level' => 'high', 'narasi' => 'narasi high 1'],
                    ],
                ]],
            ],
        ]);
    }

    public function test_guest_cannot_list_reports(): void
    {
        $this->getJson('/api/reports')->assertUnauthorized();
    }

    public function test_index_only_lists_own_reports(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $this->completedReportFor($owner);
        $this->completedReportFor($stranger);

        $response = $this->actingAs($owner, 'sanctum')->getJson('/api/reports');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_owner_can_view_own_report(): void
    {
        $owner = User::factory()->create();
        $report = $this->completedReportFor($owner);

        $response = $this->actingAs($owner, 'sanctum')->getJson("/api/reports/{$report->id}");

        $response->assertOk()->assertJsonPath('id', $report->id);

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'lihat_laporan',
            'target_type' => PersonalityReport::class,
            'target_id' => $report->id,
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_stranger_cannot_view_report_and_denial_is_audited(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $report = $this->completedReportFor($owner);

        $response = $this->actingAs($stranger, 'sanctum')->getJson("/api/reports/{$report->id}");

        $response->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'aksi' => 'lihat_laporan_ditolak',
            'target_type' => PersonalityReport::class,
            'target_id' => $report->id,
            'actor_user_id' => $stranger->id,
        ]);
    }

    public function test_owner_can_download_pdf_for_completed_report(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $report = $this->completedReportFor($owner);

        $response = $this->actingAs($owner, 'sanctum')->get("/api/reports/{$report->id}/pdf");

        $response->assertOk();
        Storage::disk('local')->assertExists("reports/laporan-{$report->id}.pdf");
    }

    public function test_pdf_download_rejected_when_report_not_completed(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $report = $this->completedReportFor($owner);
        $report->update(['status' => 'generating']);

        $response = $this->actingAs($owner, 'sanctum')->get("/api/reports/{$report->id}/pdf");

        $response->assertStatus(422);
    }

    public function test_stranger_cannot_download_pdf(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $report = $this->completedReportFor($owner);

        $response = $this->actingAs($stranger, 'sanctum')->get("/api/reports/{$report->id}/pdf");

        $response->assertForbidden();
    }

    // --- Fitur Supervisor, 2026-09-08: otorisasi aditif company-scoped.

    public function test_supervisor_can_view_and_download_report_of_candidate_in_same_company(): void
    {
        Storage::fake('local');
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $candidate = User::factory()->create(['role' => 'user']);
        $report = $this->completedReportForHrCandidate($hr, $candidate);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('id', $report->id);

        $this->actingAs($supervisor, 'sanctum')
            ->get("/api/reports/{$report->id}/pdf")
            ->assertOk();
    }

    public function test_supervisor_index_lists_reports_from_all_hr_in_company(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hrOne = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $hrTwo = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $this->completedReportForHrCandidate($hrOne, User::factory()->create());
        $this->completedReportForHrCandidate($hrTwo, User::factory()->create());

        // Company lain tidak boleh bocor.
        $otherCompany = Company::create(['name' => 'PT Lain']);
        $otherHr = User::factory()->create(['role' => 'hr', 'company_id' => $otherCompany->id]);
        $this->completedReportForHrCandidate($otherHr, User::factory()->create());

        $response = $this->actingAs($supervisor, 'sanctum')->getJson('/api/reports');

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_supervisor_from_different_company_cannot_view_report(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $otherCompany = Company::create(['name' => 'PT Lain']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $otherCompany->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $report = $this->completedReportForHrCandidate($hr, User::factory()->create());

        $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/reports/{$report->id}")
            ->assertForbidden();
    }

    public function test_index_search_filter_matches_candidate_name(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $this->completedReportForHrCandidate($hr, User::factory()->create(['name' => 'Budi Santoso']));
        $this->completedReportForHrCandidate($hr, User::factory()->create(['name' => 'Siti Aminah']));

        $response = $this->actingAs($supervisor, 'sanctum')->getJson('/api/reports?search=Budi');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sample.user.name', 'Budi Santoso');
    }
}
