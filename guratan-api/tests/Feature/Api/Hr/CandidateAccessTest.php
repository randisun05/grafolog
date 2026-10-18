<?php

namespace Tests\Feature\Api\Hr;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Support\MinimalPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Akun kandidat B2B (password sementara dikelola HR) dan jalur unggah scan oleh kandidat (2026-10-18). */
class CandidateAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::fake();
        config(['payment.bypass' => true]);
    }

    private function hr(): User
    {
        return User::factory()->create(['role' => 'hr', 'company_id' => Company::create(['name' => 'PT Uji'])->id]);
    }

    /** actingAs() mengganti guard bawaan ke sanctum; login sungguhan butuh guard `web` kembali. */
    private function loginAs(string $email, string $password)
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    private function addCandidate(User $hr, string $email = 'ani@calon.test'): array
    {
        return $this->actingAs($hr, 'sanctum')->postJson('/api/hr/candidates', [
            'name' => 'Ani Calon', 'email' => $email, 'consent_confirmed' => true,
        ])->assertCreated()->json();
    }

    public function test_new_candidate_gets_temp_password_shown_once_and_must_change_it(): void
    {
        $hr = $this->hr();
        $created = $this->addCandidate($hr);
        $this->assertNotEmpty($created['temp_password']);

        $login = $this->loginAs('ani@calon.test', $created['temp_password'])
            ->assertOk()->assertJsonPath('user.must_change_password', true);
        $this->assertNull($this->actingAs($hr, 'sanctum')->getJson('/api/hr/candidates')->json('0.temp_password'));

        // Ganti password: flag mati, token lain dicabut, password lama tidak berlaku.
        $token = $login->json('token');
        $this->app['auth']->forgetGuards(); // lepas HR yang masih terpasang oleh actingAs
        $this->app['auth']->shouldUse('sanctum');
        $this->withToken($token)->putJson('/api/auth/change-password', [
            'current_password' => 'salah', 'password' => 'KataBaru123', 'password_confirmation' => 'KataBaru123',
        ])->assertUnprocessable();
        $this->withToken($token)->putJson('/api/auth/change-password', [
            'current_password' => $created['temp_password'], 'password' => 'KataBaru123', 'password_confirmation' => 'KataBaru123',
        ])->assertOk()->assertJsonPath('user.must_change_password', false);
        $this->loginAs('ani@calon.test', $created['temp_password'])->assertUnprocessable();
    }

    public function test_hr_resets_password_only_for_accounts_created_by_b2b(): void
    {
        $hr = $this->hr();
        $created = $this->addCandidate($hr);
        $reset = $this->actingAs($hr, 'sanctum')->postJson("/api/hr/candidates/{$created['id']}/reset-password")
            ->assertOk()->json();
        $this->assertNotSame($created['temp_password'], $reset['temp_password']);
        $this->loginAs('ani@calon.test', $reset['temp_password'])->assertOk();

        // Klien mandiri yang ditautkan ke perusahaan tidak boleh di-reset HR.
        $self = User::factory()->create(['role' => 'user', 'email' => 'mandiri@example.com', 'acquisition_source' => 'website']);
        $profile = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/candidates', [
            'name' => 'Mandiri', 'email' => 'mandiri@example.com', 'consent_confirmed' => true,
        ])->assertCreated();
        $this->assertNull($profile->json('temp_password'));
        $this->actingAs($hr, 'sanctum')->postJson('/api/hr/candidates/'.$profile->json('id').'/reset-password')->assertForbidden();

        // HR perusahaan lain tidak bisa.
        $other = $this->hr();
        $this->actingAs($other, 'sanctum')->postJson("/api/hr/candidates/{$created['id']}/reset-password")->assertNotFound();
    }

    public function test_csv_import_returns_credentials_for_new_accounts_only(): void
    {
        $hr = $this->hr();
        User::factory()->create(['role' => 'user', 'email' => 'ada@example.com']);
        $csv = UploadedFile::fake()->createWithContent('k.csv', "name,email\nBaru,baru@example.com\nAda,ada@example.com\n");

        $res = $this->actingAs($hr, 'sanctum')->post('/api/hr/candidates/import', ['file' => $csv, 'consent_confirmed' => true], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('imported', 2)->assertJsonCount(1, 'credentials');
        $this->assertSame('baru@example.com', $res->json('credentials.0.email'));
    }

    public function test_candidate_uploads_own_scan_pdf_and_sees_only_tasks(): void
    {
        $hr = $this->hr();
        $grafolog = User::factory()->create(['role' => 'grafolog', 'grafolog_status' => 'verified']);
        $a = $this->addCandidate($hr, 'a@calon.test');
        $b = $this->addCandidate($hr, 'b@calon.test');
        $batch = $this->actingAs($hr, 'sanctum')->postJson('/api/hr/orders', [
            'product' => 'paket-rekrutmen', 'candidate_ids' => [$a['id'], $b['id']], 'grafolog_id' => $grafolog->id,
        ])->assertCreated()->json();
        $this->actingAs($hr, 'sanctum')->postJson("/api/hr/orders/{$batch['id']}/payment")->assertCreated();

        $candA = User::findOrFail($a['user_id']);
        $candB = User::findOrFail($b['user_id']);
        $tasks = $this->actingAs($candA, 'sanctum')->getJson('/api/candidate/tasks')->assertOk()->assertJsonCount(1)->json();
        $this->assertSame('PT Uji', $tasks[0]['company']);
        $this->assertTrue($tasks[0]['paid']);
        $this->assertSame(0, $tasks[0]['scan_count']);

        $upload = fn (User $u, int $sampleId) => $this->actingAs($u, 'sanctum')->post("/api/samples/{$sampleId}/images", [
            'images' => [UploadedFile::fake()->createWithContent('scan.pdf', MinimalPdf::make())],
        ], ['Accept' => 'application/json']);

        $upload($candA, $tasks[0]['sample_id'])->assertCreated();
        $upload($candB, $tasks[0]['sample_id'])->assertForbidden(); // kandidat lain tidak bisa mengisi punya orang
        $this->actingAs($candA, 'sanctum')->getJson('/api/candidate/tasks')->assertJsonPath('0.scan_count', 1);

        // Kandidat tidak melihat pesanan perusahaan; HR melihat scan yang diunggah kandidat.
        $this->actingAs($candA, 'sanctum')->getJson('/api/orders')->assertJsonCount(0);
        $row = collect($this->actingAs($hr, 'sanctum')->getJson('/api/hr/candidates')->json())->firstWhere('user_id', $candA->id);
        $this->assertSame(1, $row['orders'][0]['image_count']);
        $this->assertSame(Order::STATUS_PROCESSING, $row['orders'][0]['status']);
    }

    public function test_non_candidate_roles_have_no_tasks_endpoint(): void
    {
        $this->actingAs($this->hr(), 'sanctum')->getJson('/api/candidate/tasks')->assertForbidden();
    }
}
