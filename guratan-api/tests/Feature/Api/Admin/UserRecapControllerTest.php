<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRecapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access(): void
    {
        $this->getJson('/api/admin/recap/users')->assertUnauthorized();
        $this->get('/api/admin/recap/users/export')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->getJson('/api/admin/recap/users')->assertForbidden();
    }

    public function test_administrator_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'user', 'name' => 'Klien Satu']);
        User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/users');

        $response->assertOk();
        // administrator (self) + user + grafolog
        $this->assertSame(3, $response->json('total'));
    }

    public function test_filters_by_role(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'user']);
        User::factory()->create(['role' => 'grafolog']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/recap/users?role=grafolog');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.role', 'grafolog');
    }

    public function test_filters_by_company_and_search(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Contoh']);
        $hr = User::factory()->create(['role' => 'hr', 'company_id' => $company->id, 'name' => 'Budi HR']);
        User::factory()->create(['role' => 'hr', 'name' => 'Lain']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/recap/users?company_id={$company->id}&search=Budi");

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hr->id);
    }

    public function test_export_returns_csv_and_logs_audit(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'user', 'name' => 'Klien CSV', 'email' => 'klien-csv@example.com']);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/admin/recap/users/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('Klien CSV', $content);
        $this->assertStringContainsString('klien-csv@example.com', $content);

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ekspor_rekap_pengguna', 'actor_user_id' => $admin->id]);
    }
}
