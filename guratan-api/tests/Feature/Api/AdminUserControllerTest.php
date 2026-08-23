<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_or_create_admin_users(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->postJson('/api/admin/users', [])->assertUnauthorized();
    }

    public function test_non_administrator_roles_are_forbidden(): void
    {
        foreach (['user', 'grafolog'] as $role) {
            $actor = User::factory()->create(['role' => $role]);

            $this->actingAs($actor, 'sanctum')->getJson('/api/admin/users')->assertForbidden();
            $this->actingAs($actor, 'sanctum')->postJson('/api/admin/users', [
                'name' => 'X', 'email' => "x-$role@example.com",
                'password' => 'password123', 'password_confirmation' => 'password123',
                'role' => 'supervisor',
            ])->assertForbidden();
        }
    }

    public function test_administrator_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_administrator_can_create_supervisor_account(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Supervisor Baru',
            'email' => 'supervisor-new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'supervisor',
        ]);

        $response->assertCreated()->assertJsonPath('role', 'supervisor');
        $this->assertDatabaseHas('users', ['email' => 'supervisor-new@example.com', 'role' => 'supervisor']);
    }

    public function test_administrator_can_create_another_administrator_account(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Admin Kedua',
            'email' => 'admin-two@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'administrator',
        ]);

        $response->assertCreated()->assertJsonPath('role', 'administrator');
    }

    public function test_created_staff_account_has_a_working_hashed_password(): void
    {
        // Checks the hash directly rather than calling POST /auth/login:
        // within a single test method, the test client's HTTP kernel
        // resolves 'auth' to the sanctum guard after the admin-authenticated
        // request above, which then breaks Auth::attempt()'s dependency on
        // the 'web' guard (RequestGuard has no attempt()). That's a test-
        // harness quirk, not a production behavior - real requests each get
        // a fresh guard resolution. /auth/login itself is already covered
        // end-to-end by AuthControllerTest.
        $admin = User::factory()->create(['role' => 'administrator']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Grafolog Baru',
            'email' => 'grafolog-new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'grafolog',
        ])->assertCreated();

        $created = User::where('email', 'grafolog-new@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_administrator_can_create_hr_account_with_company(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Nusantara Rekrut']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'HR Baru',
            'email' => 'hr-new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'hr',
            'company_id' => $company->id,
        ]);

        $response->assertCreated()->assertJsonPath('role', 'hr')->assertJsonPath('company_id', $company->id);
    }

    public function test_hr_account_requires_company_id(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'HR Tanpa Company',
            'email' => 'hr-no-company@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'hr',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('company_id');
    }

    public function test_non_hr_account_rejects_company_id(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $company = Company::create(['name' => 'PT Iseng']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Grafolog Salah Field',
            'email' => 'grafolog-wrong-field@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'grafolog',
            'company_id' => $company->id,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('company_id');
    }

    public function test_cannot_create_staff_account_with_client_role(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'Bukan Staf',
            'email' => 'not-staff@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    // --- update ---

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'company_id' => $user->company_id,
            'is_active' => true,
        ], $overrides);
    }

    public function test_administrator_can_edit_staff_account_fields(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'name' => 'Nama Lama']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson(
            "/api/admin/users/{$grafolog->id}",
            $this->updatePayload($grafolog, ['name' => 'Nama Baru', 'email' => 'baru@example.com'])
        );

        $response->assertOk()->assertJsonPath('name', 'Nama Baru');
        $this->assertDatabaseHas('users', ['id' => $grafolog->id, 'name' => 'Nama Baru', 'email' => 'baru@example.com']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_akun_staf', 'target_id' => $grafolog->id]);
    }

    public function test_administrator_can_deactivate_staff_account_and_it_revokes_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $grafolog->createToken('guratan-web');
        $this->assertSame(1, $grafolog->tokens()->count());

        $response = $this->actingAs($admin, 'sanctum')->patchJson(
            "/api/admin/users/{$grafolog->id}",
            $this->updatePayload($grafolog, ['is_active' => false])
        );

        $response->assertOk()->assertJsonPath('is_active', false);
        $this->assertSame(0, $grafolog->tokens()->count());
    }

    public function test_administrator_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->patchJson(
            "/api/admin/users/{$admin->id}",
            $this->updatePayload($admin, ['is_active' => false])
        );

        $response->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_administrator_can_reset_staff_password(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $grafolog = User::factory()->create(['role' => 'grafolog', 'password' => 'password-lama123']);

        $this->actingAs($admin, 'sanctum')->patchJson(
            "/api/admin/users/{$grafolog->id}",
            $this->updatePayload($grafolog, ['password' => 'password-baru123', 'password_confirmation' => 'password-baru123'])
        )->assertOk();

        $this->assertTrue(Hash::check('password-baru123', $grafolog->fresh()->password));
    }

    public function test_cannot_edit_client_account_via_staff_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $client = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin, 'sanctum')->patchJson(
            "/api/admin/users/{$client->id}",
            ['name' => $client->name, 'email' => $client->email, 'role' => 'grafolog', 'is_active' => true]
        )->assertNotFound();
    }

    public function test_non_administrator_cannot_update_staff_account(): void
    {
        $actor = User::factory()->create(['role' => 'grafolog']);
        $target = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($actor, 'sanctum')->patchJson(
            "/api/admin/users/{$target->id}",
            $this->updatePayload($target)
        )->assertForbidden();
    }
}
