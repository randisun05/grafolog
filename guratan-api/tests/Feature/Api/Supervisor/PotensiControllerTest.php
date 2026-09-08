<?php

namespace Tests\Feature\Api\Supervisor;

use App\Models\Aspek;
use App\Models\Company;
use App\Models\HandwritingSample;
use App\Models\PersonalityReport;
use App\Models\Project;
use App\Models\Sindrom;
use App\Models\Topik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class PotensiControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private Sindrom $sindrom;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed sekali - dipakai bersama semua fixture report supaya laporan
        // yang berbeda genuinely merujuk aspek '01' yang SAMA, bukan aspek
        // baru per panggilan (yang akan bikin rata-rata jadi tidak berarti).
        $this->sindrom = $this->seedMinimalAspek(1);
    }

    private function completedReportForHrCandidate(User $hr, User $candidate, int $skor): PersonalityReport
    {
        $sindrom = $this->sindrom;
        $project = Project::create(['source' => 'hr', 'created_by' => $hr->id]);
        $sample = HandwritingSample::create([
            'project_id' => $project->id, 'user_id' => $candidate->id,
            'created_by' => $hr->id, 'tier' => 'comprehensive', 'status' => 'completed',
        ]);

        return PersonalityReport::create([
            'sample_id' => $sample->id, 'tier' => 'comprehensive', 'status' => 'completed', 'generated_at' => now(),
            'data' => [
                'sindrom' => [[
                    'id' => $sindrom->id, 'kode_romawi' => $sindrom->kode_romawi, 'nama' => $sindrom->nama,
                    'polaritas' => 'HIJAU', 'catatan_polaritas' => null, 'rata_rata_skor' => $skor, 'band_label_rata_rata' => 'x',
                    'aspek' => [
                        ['kode' => '01', 'nama' => 'Aspek Karier', 'skor' => $skor, 'band_label' => 'x', 'narasi_level' => 'high', 'narasi' => 'x'],
                    ],
                ]],
            ],
        ]);
    }

    public function test_guest_cannot_access_potensi_endpoint(): void
    {
        $this->getJson('/api/supervisor/potensi')->assertUnauthorized();
    }

    public function test_non_supervisor_cannot_access_potensi_endpoint(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->getJson('/api/supervisor/potensi')->assertForbidden();
    }

    public function test_supervisor_without_company_gets_clean_error(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => null]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/supervisor/potensi')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Akun Supervisor Anda belum terikat ke perusahaan.');
    }

    public function test_supervisor_gets_aggregate_matching_seeded_reports(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $this->completedReportForHrCandidate($hr, User::factory()->create(), 6);
        $this->completedReportForHrCandidate($hr, User::factory()->create(), 8);

        $response = $this->actingAs($supervisor, 'sanctum')->getJson('/api/supervisor/potensi');

        $response->assertOk()
            ->assertJsonPath('report_count', 2)
            ->assertJsonPath('candidate_count', 2);
        $aspek01 = collect($response->json('sindrom.0.aspek'))->firstWhere('kode', '01');
        $this->assertEquals(7.0, $aspek01['avg_skor']);
    }

    public function test_potensi_does_not_leak_across_companies(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $otherCompany = Company::create(['name' => 'PT Lain']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $otherHr = User::factory()->create(['role' => 'hr', 'company_id' => $otherCompany->id]);
        $this->completedReportForHrCandidate($hr, User::factory()->create(), 7);
        $this->completedReportForHrCandidate($otherHr, User::factory()->create(), 7);

        $response = $this->actingAs($supervisor, 'sanctum')->getJson('/api/supervisor/potensi');

        $response->assertOk()->assertJsonPath('report_count', 1);
    }

    public function test_topik_ids_query_param_filters_result(): void
    {
        $company = Company::create(['name' => 'PT Uji Coba']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'company_id' => $company->id]);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
        $this->completedReportForHrCandidate($hr, User::factory()->create(), 7);
        $lain = Topik::create(['nama' => 'Tidak Terpakai']);

        $response = $this->actingAs($supervisor, 'sanctum')
            ->getJson("/api/supervisor/potensi?topik_ids[]={$lain->id}");

        $response->assertOk()->assertJsonCount(0, 'sindrom');
    }
}
