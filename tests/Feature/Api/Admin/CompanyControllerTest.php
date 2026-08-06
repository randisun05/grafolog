<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_or_create_companies(): void
    {
        $this->getJson('/api/admin/companies')->assertUnauthorized();
        $this->postJson('/api/admin/companies', [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $hr = User::factory()->create(['role' => 'hr']);

        $this->actingAs($hr, 'sanctum')->postJson('/api/admin/companies', ['name' => 'PT Contoh'])
            ->assertForbidden();
    }

    public function test_administrator_can_create_and_list_companies(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);

        $create = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', [
            'name' => 'PT Nusantara Rekrut',
        ]);
        $create->assertCreated()->assertJsonPath('name', 'PT Nusantara Rekrut');

        $list = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/companies');
        $list->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_duplicate_company_name_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', ['name' => 'PT Dup'])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/companies', ['name' => 'PT Dup'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }
}
