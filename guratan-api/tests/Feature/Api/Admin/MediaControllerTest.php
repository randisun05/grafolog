<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_upload(): void
    {
        $this->postJson('/api/admin/media', ['image' => UploadedFile::fake()->image('x.jpg')])
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_upload(): void
    {
        $user = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/admin/media', ['image' => UploadedFile::fake()->image('x.jpg')])
            ->assertForbidden();
    }

    public function test_admin_can_upload_image_and_gets_url_back(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/media', ['image' => UploadedFile::fake()->image('x.jpg')]);

        $response->assertOk()->assertJsonStructure(['url']);
        $this->assertStringContainsString('/storage/uploads/', $response->json('url'));
    }

    public function test_rejects_non_image_file(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/media', ['image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])
            ->assertUnprocessable();
    }
}
