<?php

namespace Tests\Feature\Api;

use App\Mail\GrafologVerificationMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GrafologRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $override = []): TestResponse
    {
        return $this->postJson('/api/auth/register-grafolog', $override + [
            'name' => 'Grafolog Baru', 'email' => 'baru@example.com', 'phone' => '08123',
            'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'certificate_number' => 'CERT-001',
            'certificate' => UploadedFile::fake()->create('sertifikat.pdf', 200, 'application/pdf'),
            'consent' => true,
        ]);
    }

    public function test_self_registered_grafolog_is_pending_and_blocked(): void
    {
        Storage::fake('local');
        $res = $this->register()->assertCreated()->assertJsonPath('user.grafolog_status', 'pending');
        $this->assertArrayNotHasKey('certificate_path', $res->json('user'));

        $user = User::where('email', 'baru@example.com')->first();
        Storage::disk('local')->assertExists($user->certificate_path);
        $this->assertFalse($user->isGrafolog());

        // Semua aksi grafolog ditolak sampai diverifikasi.
        $this->actingAs($user, 'sanctum')->getJson('/api/users/lookup?email=x@example.com')->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/clients', ['consent_confirmed' => true, 'name' => 'K', 'email' => 'k@example.com'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/tokens/wallet')->assertForbidden();
    }

    public function test_certificate_is_required(): void
    {
        $this->register(['certificate' => null])->assertStatus(422)->assertJsonValidationErrors('certificate');
    }

    public function test_admin_approves_and_grafolog_gains_access(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->register();
        $user = User::where('email', 'baru@example.com')->first();
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/grafolog-applications')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.has_certificate', true);
        $this->actingAs($admin, 'sanctum')->get("/api/admin/grafolog-applications/{$user->id}/certificate")->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/grafolog-applications/{$user->id}/decision", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('grafolog_status', 'verified');

        $this->assertTrue($user->fresh()->isGrafolog());
        Mail::assertQueued(GrafologVerificationMail::class);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'verifikasi_grafolog', 'target_id' => $user->id]);
        $this->actingAs($admin, 'sanctum')->getJson('/api/grafologs')->assertJsonFragment(['email' => 'baru@example.com']);
    }

    public function test_reject_requires_note_and_pending_grafolog_hidden_from_assignment_list(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->register();
        $user = User::where('email', 'baru@example.com')->first();
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/grafologs')->assertJsonMissing(['email' => 'baru@example.com']);
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/grafolog-applications/{$user->id}/decision", ['decision' => 'reject'])
            ->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/grafolog-applications/{$user->id}/decision", ['decision' => 'reject', 'note' => 'Sertifikat tidak terbaca'])
            ->assertOk()->assertJsonPath('grafolog_status', 'rejected');
        $this->assertFalse($user->fresh()->isGrafolog());
    }

    public function test_non_admin_cannot_review_applications(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'supervisor']), 'sanctum')
            ->getJson('/api/admin/grafolog-applications')->assertForbidden();
    }
}
