<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_grafologs(): void
    {
        $this->getJson('/api/grafologs')->assertUnauthorized();
    }

    public function test_client_cannot_list_grafologs(): void
    {
        $client = User::factory()->create(['role' => 'user']);

        $this->actingAs($client, 'sanctum')->getJson('/api/grafologs')->assertForbidden();
    }

    public function test_hr_can_list_grafologs(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);
        User::factory()->count(2)->create(['role' => 'grafolog']);
        User::factory()->create(['role' => 'user']); // tidak boleh ikut kehitung

        $response = $this->actingAs($hr, 'sanctum')->getJson('/api/grafologs');

        $response->assertOk()->assertJsonCount(2);
    }

    public function test_administrator_can_list_grafologs(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($admin, 'sanctum')->getJson('/api/grafologs')->assertOk()->assertJsonCount(1);
    }

    public function test_guest_cannot_create_client(): void
    {
        $this->postJson('/api/clients', ['name' => 'Klien Baru', 'email' => 'klien@example.com'])
            ->assertUnauthorized();
    }

    public function test_non_grafolog_cannot_create_client(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')
            ->postJson('/api/clients', ['name' => 'Klien Baru', 'email' => 'klien@example.com'])
            ->assertForbidden();
    }

    public function test_grafolog_can_create_client_with_explicit_password(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson('/api/clients', [
            'name' => 'Klien Walk-in',
            'email' => 'walkin@example.com',
            'password' => 'Password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Klien Walk-in')
            ->assertJsonPath('email', 'walkin@example.com')
            ->assertJsonPath('generated_password', null);
        $this->assertDatabaseHas('users', ['email' => 'walkin@example.com', 'role' => 'user']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'daftarkan_klien', 'actor_user_id' => $grafolog->id]);
    }

    public function test_grafolog_can_create_client_without_password_and_it_actually_works(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson('/api/clients', [
            'name' => 'Klien Tanpa Sandi',
            'email' => 'nopassword@example.com',
        ]);

        $response->assertCreated();
        $generatedPassword = $response->json('generated_password');
        $this->assertNotEmpty($generatedPassword);

        // Password yang di-generate harus benar-benar cocok dengan hash yang
        // tersimpan - dicek langsung lewat Hash::check(), bukan HTTP login
        // sungguhan (memanggil /api/auth/login di dalam test yang sudah
        // actingAs() mengacaukan resolusi guard default Laravel).
        $client = User::where('email', 'nopassword@example.com')->first();
        $this->assertTrue(Hash::check($generatedPassword, $client->password));
    }

    public function test_duplicate_email_rejected(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        User::factory()->create(['email' => 'sudahada@example.com']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson('/api/clients', ['name' => 'Duplikat', 'email' => 'sudahada@example.com'])
            ->assertUnprocessable();
    }
}
