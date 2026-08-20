<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\MeasurementVariable;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndikatorRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function indikator(): Indikator
    {
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);
        $aspek = Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);

        return Indikator::create(['kode' => "01-1'", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Middle zone height large']);
    }

    /** Kedua Indikator yang berbeda - dipakai test rule indikator_checked (butuh sumber & target). */
    private function otherIndikator(): Indikator
    {
        $sindrom = Sindrom::firstOrCreate(['kode_romawi' => 'I'], ['nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);
        $aspek = Aspek::firstOrCreate(['kode' => '01'], ['sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);

        return Indikator::create(['kode' => "01-2'", 'posisi' => 2, 'aspek_id' => $aspek->id, 'nama' => 'Indikator lain']);
    }

    private function variableWithCategories(): MeasurementVariable
    {
        $variable = MeasurementVariable::create(['kode' => '1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $variable->kategori()->create(['kategori' => 'large', 'urutan' => 1]);
        $variable->kategori()->create(['kategori' => 'small', 'urutan' => 2]);

        return $variable;
    }

    public function test_guest_cannot_manage_rules(): void
    {
        $indikator = $this->indikator();
        $this->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [])->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        $this->actingAs($grafolog, 'sanctum')
            ->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
                'rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large',
            ])->assertForbidden();
    }

    public function test_administrator_can_create_category_rule(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'category',
            'variable_a_id' => $variable->id,
            'category_label' => 'large',
        ]);

        $response->assertCreated()->assertJsonPath('category_label', 'large')->assertJsonPath('variable_a.kode', '1');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'buat_aturan_indikator', 'actor_user_id' => $admin->id]);
    }

    public function test_category_rule_with_unknown_label_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'category',
            'variable_a_id' => $variable->id,
            'category_label' => 'tidak-ada-kategori-ini',
        ])->assertUnprocessable()->assertJsonValidationErrors(['category_label']);
    }

    public function test_category_rule_with_operator_field_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'category',
            'variable_a_id' => $variable->id,
            'category_label' => 'large',
            'operator' => 'equals',
        ])->assertUnprocessable()->assertJsonValidationErrors(['operator']);
    }

    public function test_administrator_can_create_comparison_rule_with_variable_b(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variableA = $this->variableWithCategories();
        $variableB = MeasurementVariable::create(['kode' => '2', 'axis' => 'vertical', 'nama' => 'Ovals height']);

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'comparison',
            'variable_a_id' => $variableA->id,
            'operator' => 'greater_than',
            'koefisien' => 1.5,
            'variable_b_id' => $variableB->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('operator', 'greater_than')
            ->assertJsonPath('variable_b.kode', '2');
    }

    public function test_administrator_can_create_comparison_rule_with_compare_value(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'comparison',
            'variable_a_id' => $variable->id,
            'operator' => 'greater_than',
            'compare_value' => 5.2,
        ]);

        // decimal columns come back as a string on MySQL (PDO preserves precision)
        // but a native float on the sqlite test driver - assert the numeric value,
        // not a specific string format, so this test isn't driver-fragile.
        $response->assertCreated();
        $this->assertEquals(5.2, (float) $response->json('compare_value'));
    }

    public function test_comparison_rule_with_both_variable_b_and_compare_value_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variableA = $this->variableWithCategories();
        $variableB = MeasurementVariable::create(['kode' => '2', 'axis' => 'vertical', 'nama' => 'Ovals height']);

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'comparison',
            'variable_a_id' => $variableA->id,
            'operator' => 'equals',
            'variable_b_id' => $variableB->id,
            'compare_value' => 5.2,
        ])->assertUnprocessable()->assertJsonValidationErrors(['variable_b_id']);
    }

    public function test_comparison_rule_with_neither_variable_b_nor_compare_value_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'comparison',
            'variable_a_id' => $variable->id,
            'operator' => 'equals',
        ])->assertUnprocessable()->assertJsonValidationErrors(['variable_b_id']);
    }

    public function test_comparison_rule_missing_operator_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'comparison',
            'variable_a_id' => $variable->id,
            'compare_value' => 5.2,
        ])->assertUnprocessable()->assertJsonValidationErrors(['operator']);
    }

    public function test_administrator_can_update_rule(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();
        $rule = $indikator->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/indikator-rules/{$rule->id}", [
            'rule_type' => 'category',
            'variable_a_id' => $variable->id,
            'category_label' => 'small',
        ]);

        $response->assertOk()->assertJsonPath('category_label', 'small');
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_aturan_indikator', 'actor_user_id' => $admin->id]);
    }

    public function test_administrator_can_delete_rule(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();
        $rule = $indikator->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/indikator-rules/{$rule->id}")
            ->assertOk();

        $this->assertDatabaseMissing('indikator_rules', ['id' => $rule->id]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'hapus_aturan_indikator', 'actor_user_id' => $admin->id]);
    }

    public function test_deleting_indikator_cascades_its_rules(): void
    {
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();
        $rule = $indikator->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);

        $indikator->delete();

        $this->assertDatabaseMissing('indikator_rules', ['id' => $rule->id]);
    }

    public function test_cannot_delete_measurement_variable_used_by_a_rule(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $variable = $this->variableWithCategories();
        $indikator->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/knowledge/measurement-variables/{$variable->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('measurement_variable', ['id' => $variable->id]);
    }

    public function test_indikator_update_accepts_rule_group_logic(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();

        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/knowledge/indikator/{$indikator->id}", [
            'rule_group_logic' => 'AND',
        ])->assertOk()->assertJsonPath('rule_group_logic', 'AND');
    }

    public function test_new_indikator_rule_group_logic_defaults_to_or(): void
    {
        $indikator = $this->indikator();

        $this->assertSame('OR', $indikator->fresh()->rule_group_logic);
    }

    // --- indikator_checked (unifikasi cross-reference, 2026-08-19) -------

    public function test_administrator_can_create_indikator_checked_rule(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $source = $this->otherIndikator();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'indikator_checked',
            'depends_on_indikator_id' => $source->id,
        ]);

        $response->assertCreated()->assertJsonPath('depends_on_indikator.id', $source->id);
        $this->assertDatabaseHas('indikator_rules', [
            'indikator_id' => $indikator->id, 'rule_type' => 'indikator_checked', 'depends_on_indikator_id' => $source->id,
        ]);
    }

    public function test_indikator_checked_rule_requires_depends_on_indikator_id(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'indikator_checked',
        ])->assertUnprocessable()->assertJsonValidationErrors('depends_on_indikator_id');
    }

    public function test_indikator_checked_rule_rejects_measurement_fields(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $source = $this->otherIndikator();
        $variable = $this->variableWithCategories();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'indikator_checked',
            'depends_on_indikator_id' => $source->id,
            'variable_a_id' => $variable->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('variable_a_id');
    }

    public function test_indikator_checked_rule_cannot_depend_on_itself(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'indikator_checked',
            'depends_on_indikator_id' => $indikator->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('depends_on_indikator_id');
    }

    public function test_category_rule_rejects_depends_on_indikator_id(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $indikator = $this->indikator();
        $source = $this->otherIndikator();
        $variable = $this->variableWithCategories();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/knowledge/indikator/{$indikator->id}/rules", [
            'rule_type' => 'category',
            'variable_a_id' => $variable->id,
            'category_label' => 'large',
            'depends_on_indikator_id' => $source->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('depends_on_indikator_id');
    }

    public function test_deleting_indikator_cascades_dependent_indikator_checked_rules(): void
    {
        $indikator = $this->indikator();
        $source = $this->otherIndikator();
        $rule = $indikator->rules()->create(['rule_type' => 'indikator_checked', 'depends_on_indikator_id' => $source->id]);

        $source->delete();

        $this->assertDatabaseMissing('indikator_rules', ['id' => $rule->id]);
    }
}
