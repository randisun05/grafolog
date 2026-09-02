<?php

namespace Tests\Feature\Api;

use App\Models\GrafologApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pendaftaran grafolog lewat verifikasi data - lihat migrasi
 * create_grafolog_applications_table untuk konteks alur penuh.
 */
class GrafologApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Grafolog Satu',
            'email' => 'grafolog-baru@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '08123456789',
            'catatan' => 'Sertifikat grafologi dari lembaga X, 5 tahun pengalaman.',
            'document' => UploadedFile::fake()->create('sertifikat.pdf', 500, 'application/pdf'),
        ], $overrides);
    }

    public function test_guest_can_submit_application(): void
    {
        Storage::fake('local');

        $response = $this->postJson('/api/grafolog-applications', $this->validPayload());

        $response->assertCreated()->assertJsonStructure(['message']);
        $this->assertDatabaseHas('grafolog_applications', [
            'email' => 'grafolog-baru@example.com',
            'status' => 'pending',
        ]);

        $application = GrafologApplication::first();
        Storage::disk('local')->assertExists($application->document_path);
        // Password disimpan hashed, bukan plain text.
        $this->assertNotEquals('password123', $application->password);
    }

    public function test_submit_does_not_issue_token_or_create_user(): void
    {
        Storage::fake('local');

        $this->postJson('/api/grafolog-applications', $this->validPayload())->assertCreated();

        $this->assertDatabaseMissing('users', ['email' => 'grafolog-baru@example.com']);
    }

    public function test_rejects_email_already_registered_as_user(): void
    {
        Storage::fake('local');
        User::factory()->create(['email' => 'sudah-ada@example.com']);

        $this->postJson('/api/grafolog-applications', $this->validPayload(['email' => 'sudah-ada@example.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rejects_duplicate_pending_application_email(): void
    {
        Storage::fake('local');
        $this->postJson('/api/grafolog-applications', $this->validPayload())->assertCreated();

        $this->postJson('/api/grafolog-applications', $this->validPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_allows_reapplying_after_previous_rejection(): void
    {
        Storage::fake('local');
        GrafologApplication::create([
            'name' => 'Lama', 'email' => 'grafolog-baru@example.com', 'password' => 'password123',
            'document_path' => 'x', 'document_original_name' => 'x.pdf', 'status' => 'rejected',
        ]);

        $this->postJson('/api/grafolog-applications', $this->validPayload())->assertCreated();
    }

    public function test_rejects_non_document_file_type(): void
    {
        Storage::fake('local');

        $this->postJson('/api/grafolog-applications', $this->validPayload([
            'document' => UploadedFile::fake()->create('sertifikat.exe', 500, 'application/octet-stream'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_document_is_required(): void
    {
        Storage::fake('local');
        $payload = $this->validPayload();
        unset($payload['document']);

        $this->postJson('/api/grafolog-applications', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('document');
    }
}
