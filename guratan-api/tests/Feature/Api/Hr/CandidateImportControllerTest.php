<?php

namespace Tests\Feature\Api\Hr;

use App\Models\Company;
use App\Models\JobPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CandidateImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function hrUser(): User
    {
        $company = Company::create(['name' => 'PT Uji Coba']);

        return User::factory()->create(['role' => 'hr', 'company_id' => $company->id]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('candidates.csv', $content);
    }

    public function test_guest_cannot_import(): void
    {
        $this->postJson('/api/hr/candidates/import', [])->assertUnauthorized();
    }

    public function test_non_hr_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->post('/api/hr/candidates/import', ['consent_confirmed' => true, 'file' => $this->csv("name,email\nA,a@example.com\n")])
            ->assertForbidden();
    }

    public function test_successful_import_creates_candidates_with_profiles_but_no_samples(): void
    {
        $hr = $this->hrUser();
        $position = JobPosition::create(['company_id' => $hr->company_id, 'title' => 'Finance Staff']);
        $csv = "name,email,phone\nBudi Kandidat,budi.kandidat@example.com,0811\nSiti Kandidat,siti.kandidat@example.com,\n";

        $response = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv($csv),
            'job_position_id' => $position->id,
        ]);

        $response->assertCreated()->assertJsonPath('imported', 2);

        $this->assertDatabaseHas('users', [
            'email' => 'budi.kandidat@example.com', 'role' => 'user', 'company_id' => $hr->company_id,
            'phone' => '0811', 'acquisition_source' => 'b2b',
        ]);
        $this->assertDatabaseCount('candidate_profiles', 2);
        $this->assertDatabaseHas('candidate_profiles', ['job_position_id' => $position->id, 'stage' => 'baru']);
        // Asesmen dipesan terpisah (berbayar) - impor tidak lagi membuat sampel.
        $this->assertDatabaseCount('handwriting_samples', 0);
    }

    public function test_import_rejects_position_of_another_company(): void
    {
        $hr = $this->hrUser();
        $other = Company::create(['name' => 'PT Lain']);
        $position = JobPosition::create(['company_id' => $other->id, 'title' => 'X']);

        $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv("name,email\nA,a@example.com\n"),
            'job_position_id' => $position->id,
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('candidate_profiles', 0);
    }

    public function test_invalid_csv_header_rejected(): void
    {
        $hr = $this->hrUser();

        $response = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv("col1,col2\nfoo,bar\n"),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_row_with_invalid_email_rejects_whole_import(): void
    {
        $hr = $this->hrUser();
        $csv = "name,email\nBudi,budi@example.com\nRusak,not-an-email\n";

        $response = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv($csv),
        ]);

        $response->assertStatus(422)->assertJsonPath('errors.0.line', 3);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('users', 1); // hanya HR-nya sendiri
    }

    public function test_email_belonging_to_staff_account_is_rejected(): void
    {
        $hr = $this->hrUser();
        $grafolog = User::factory()->create(['role' => 'grafolog', 'email' => 'staff@example.com']);
        $csv = "name,email\nStaf,staff@example.com\n";

        $response = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv($csv),
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('handwriting_samples', 0);
    }

    public function test_candidate_from_another_company_is_rejected(): void
    {
        $hr = $this->hrUser();
        $otherCompany = Company::create(['name' => 'PT Lain']);
        User::factory()->create(['role' => 'user', 'email' => 'taken@example.com', 'company_id' => $otherCompany->id]);
        $csv = "name,email\nDiambil,taken@example.com\n";

        $response = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv($csv),
        ]);

        $response->assertStatus(422);
    }

    public function test_reusing_existing_unaffiliated_client_attaches_company(): void
    {
        $hr = $this->hrUser();
        $client = User::factory()->create(['role' => 'user', 'email' => 'independen@example.com', 'company_id' => null]);
        $csv = "name,email\nIndependen,independen@example.com\n";

        $response = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['consent_confirmed' => true,
            'file' => $this->csv($csv),
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['id' => $client->id, 'company_id' => $hr->company_id]);
        $this->assertDatabaseCount('users', 2); // hr + client, tidak bikin duplikat
    }

    public function test_import_requires_consent_confirmation(): void
    {
        $hr = $this->hrUser();

        $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', [
            'file' => $this->csv("name,email\nA,a@example.com\n"),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('consent_confirmed');
    }
}
