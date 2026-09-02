<?php

namespace Tests\Feature\Api\Admin;

use App\Models\GrafologApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GrafologApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicationRecord(array $overrides = []): GrafologApplication
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('sertifikat.pdf', 500)->store('grafolog-applications', 'local');

        return GrafologApplication::create(array_merge([
            'name' => 'Calon Grafolog',
            'email' => 'calon@example.com',
            'password' => 'password123',
            'document_path' => $path,
            'document_original_name' => 'sertifikat.pdf',
        ], $overrides));
    }

    public function test_guest_cannot_access_applications(): void
    {
        $this->getJson('/api/admin/grafolog-applications')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/grafolog-applications')->assertForbidden();
    }

    public function test_administrator_can_list_and_filter_applications(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->makeApplicationRecord(['email' => 'pending@example.com']);
        $this->makeApplicationRecord(['email' => 'approved@example.com', 'status' => 'approved']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/grafolog-applications?status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('pending@example.com', $response->json('data.0.email'));
    }

    public function test_administrator_can_download_document(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $application = $this->makeApplicationRecord();

        $this->actingAs($admin, 'sanctum')
            ->get("/api/admin/grafolog-applications/{$application->id}/document")
            ->assertOk();
    }

    public function test_administrator_can_approve_application(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $application = $this->makeApplicationRecord(['email' => 'disetujui@example.com']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/grafolog-applications/{$application->id}/approve");

        $response->assertOk()
            ->assertJsonPath('email', 'disetujui@example.com')
            ->assertJsonPath('role', 'grafolog')
            ->assertJsonPath('is_active', true);

        $this->assertDatabaseHas('users', ['email' => 'disetujui@example.com', 'role' => 'grafolog', 'is_active' => true]);
        $this->assertDatabaseHas('grafolog_applications', ['id' => $application->id, 'status' => 'approved']);
        $application->refresh();
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        // Password yang baru saja diajukan (bukan yang di-generate ulang)
        // harus tetap bisa dipakai login - dicek lewat Hash::check() (bukan
        // request /auth/login sungguhan) karena actingAs(..., 'sanctum') di
        // atas sudah mengganti guard default request ini, request login
        // baru lewat Auth::attempt() bakal gagal karena alasan itu, bukan
        // karena password-nya salah.
        $newUser = User::where('email', 'disetujui@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $newUser->password));

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'setujui_akun_grafolog', 'actor_user_id' => $admin->id]);
    }

    public function test_approve_fails_if_email_already_taken(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['email' => 'sudah-ada@example.com']);
        $application = $this->makeApplicationRecord(['email' => 'sudah-ada@example.com']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/grafolog-applications/{$application->id}/approve")
            ->assertUnprocessable();

        $application->refresh();
        $this->assertSame('pending', $application->status);
    }

    public function test_approve_fails_on_already_processed_application(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $application = $this->makeApplicationRecord(['status' => 'approved']);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/grafolog-applications/{$application->id}/approve")
            ->assertUnprocessable();
    }

    public function test_administrator_can_reject_application_with_note(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $application = $this->makeApplicationRecord();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/grafolog-applications/{$application->id}/reject", [
                'review_note' => 'Dokumen tidak jelas/kurang meyakinkan.',
            ]);

        $response->assertOk()->assertJsonPath('status', 'rejected');

        $this->assertDatabaseHas('grafolog_applications', [
            'id' => $application->id,
            'status' => 'rejected',
            'review_note' => 'Dokumen tidak jelas/kurang meyakinkan.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => $application->email]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'tolak_akun_grafolog', 'actor_user_id' => $admin->id]);
    }
}
