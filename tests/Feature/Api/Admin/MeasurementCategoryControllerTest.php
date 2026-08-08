<?php

namespace Tests\Feature\Api\Admin;

use App\Models\MeasurementVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function variable(): MeasurementVariable
    {
        return MeasurementVariable::create(['kode' => '1', 'axis' => 'vertical', 'nama' => 'Uji']);
    }

    public function test_guest_cannot_manage_categories(): void
    {
        $variable = $this->variable();
        $this->postJson("/api/admin/knowledge/measurement-variables/{$variable->id}/categories", [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $variable = $this->variable();
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/admin/knowledge/measurement-variables/{$variable->id}/categories", [
                'kategori' => 'small', 'urutan' => 1,
            ])
            ->assertForbidden();
    }

    public function test_administrator_can_add_category_to_variable(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = $this->variable();

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/admin/knowledge/measurement-variables/{$variable->id}/categories",
            ['kategori' => 'extremely small', 'rentang' => '0-2', 'unit' => 'mm', 'urutan' => 1]
        );

        $response->assertCreated()->assertJsonPath('kategori', 'extremely small');
        $this->assertDatabaseHas('measurement_category', ['variable_id' => $variable->id, 'kategori' => 'extremely small']);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_kategori_ukur', 'actor_user_id' => $admin->id]);
    }

    public function test_urutan_is_required_and_must_be_positive(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = $this->variable();

        $this->actingAs($admin, 'sanctum')->postJson(
            "/api/admin/knowledge/measurement-variables/{$variable->id}/categories",
            ['kategori' => 'small', 'urutan' => 0]
        )->assertUnprocessable()->assertJsonValidationErrors(['urutan']);
    }

    public function test_administrator_can_update_category(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = $this->variable();
        $category = $variable->kategori()->create(['kategori' => 'small', 'urutan' => 1]);

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/measurement-categories/{$category->id}", [
            'kategori' => 'very small',
        ])->assertOk()->assertJsonPath('kategori', 'very small');

        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_kategori_ukur', 'actor_user_id' => $admin->id]);
    }

    public function test_administrator_can_delete_category(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $variable = $this->variable();
        $category = $variable->kategori()->create(['kategori' => 'small', 'urutan' => 1]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/measurement-categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('measurement_category', ['id' => $category->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_kategori_ukur', 'actor_user_id' => $admin->id]);
    }
}
