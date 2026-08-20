<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Aspek;
use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use App\Models\Sindrom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConceptMapControllerTest extends TestCase
{
    use RefreshDatabase;

    private function sindromWithAspek(): Sindrom
    {
        $sindrom = Sindrom::create(['kode_romawi' => 'I', 'nama' => 'Driving Forces', 'polaritas_inferred' => 'HIJAU']);
        Aspek::create(['kode' => '01', 'sindrom_id' => $sindrom->id, 'nama' => 'Authoritarian']);
        Aspek::create(['kode' => '02', 'sindrom_id' => $sindrom->id, 'nama' => 'Ego Needs']);

        return $sindrom;
    }

    public function test_guest_cannot_access_concept_map(): void
    {
        $this->getJson('/api/admin/knowledge/concept-map')->assertUnauthorized();
    }

    public function test_non_administrator_forbidden(): void
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $this->actingAs($grafolog, 'sanctum')->getJson('/api/admin/knowledge/concept-map')->assertForbidden();
    }

    public function test_overview_returns_sindrom_with_nested_aspek_and_indikator_count(): void
    {
        $sindrom = $this->sindromWithAspek();
        $aspek = Aspek::where('kode', '01')->first();
        Indikator::create(['kode' => '01-1', 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Posisi 1']);
        Indikator::create(['kode' => '01-2', 'posisi' => 2, 'aspek_id' => $aspek->id, 'nama' => 'Posisi 2']);
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/knowledge/concept-map');

        $response->assertOk()
            ->assertJsonPath('0.kode_romawi', 'I')
            ->assertJsonPath('0.aspek.0.kode', '01')
            ->assertJsonPath('0.aspek.0.indikator_count', 2);
    }

    public function test_aspek_detail_returns_indikator_with_rule_and_cross_ref_counts(): void
    {
        $sindrom = $this->sindromWithAspek();
        $aspek = Aspek::where('kode', '01')->first();
        $aspekB = Aspek::where('kode', '02')->first();
        $indikator = Indikator::create(['kode' => '01-1', 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Posisi 1']);
        $target = Indikator::create(['kode' => '02-1', 'posisi' => 1, 'aspek_id' => $aspekB->id, 'nama' => 'Target']);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $variable->kategori()->create(['kategori' => 'large', 'urutan' => 1]);
        $indikator->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);
        // cross_ref_count = jumlah Indikator LAIN yang depends_on Indikator ini.
        IndikatorRule::create(['indikator_id' => $target->id, 'rule_type' => 'indikator_checked', 'depends_on_indikator_id' => $indikator->id]);
        $admin = User::factory()->create(['role' => 'administrator']);

        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/knowledge/concept-map/aspek/{$aspek->id}");

        $response->assertOk()
            ->assertJsonPath('aspek.kode', '01')
            ->assertJsonPath('indikator.0.kode', '01-1')
            ->assertJsonPath('indikator.0.rules_count', 1)
            ->assertJsonPath('indikator.0.cross_ref_count', 1);
    }

    public function test_indikator_detail_returns_rules_and_both_cross_reference_directions(): void
    {
        $sindrom = $this->sindromWithAspek();
        $aspekA = Aspek::where('kode', '01')->first();
        $aspekB = Aspek::where('kode', '02')->first();
        $source = Indikator::create(['kode' => '01-1', 'posisi' => 1, 'aspek_id' => $aspekA->id, 'nama' => 'Sumber']);
        $target = Indikator::create(['kode' => '02-1', 'posisi' => 1, 'aspek_id' => $aspekB->id, 'nama' => 'Target']);
        $incoming = Indikator::create(['kode' => '02-2', 'posisi' => 2, 'aspek_id' => $aspekB->id, 'nama' => 'Perujuk']);

        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $variable->kategori()->create(['kategori' => 'large', 'urutan' => 1]);
        $source->rules()->create(['rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large']);

        // Keluar: target depends_on source.
        IndikatorRule::create(['indikator_id' => $target->id, 'rule_type' => 'indikator_checked', 'depends_on_indikator_id' => $source->id]);
        // Masuk: source depends_on incoming.
        IndikatorRule::create(['indikator_id' => $source->id, 'rule_type' => 'indikator_checked', 'depends_on_indikator_id' => $incoming->id]);
        // Tidak boleh muncul - rule ini TIDAK melibatkan $source sama sekali.
        $other = Indikator::create(['kode' => '02-3', 'posisi' => 3, 'aspek_id' => $aspekB->id, 'nama' => 'Tidak Terkait']);
        IndikatorRule::create(['indikator_id' => $other->id, 'rule_type' => 'indikator_checked', 'depends_on_indikator_id' => $target->id]);

        $admin = User::factory()->create(['role' => 'administrator']);
        $response = $this->actingAs($admin, 'sanctum')->getJson("/api/admin/knowledge/concept-map/indikator/{$source->id}");

        $response->assertOk()
            ->assertJsonPath('indikator.kode', '01-1')
            ->assertJsonPath('indikator.rules.0.category_label', 'large')
            ->assertJsonPath('indikator.rules.0.variable_a.nama', 'Middle zone height')
            ->assertJsonCount(1, 'referensi_keluar')
            ->assertJsonPath('referensi_keluar.0.kode', '02-1')
            ->assertJsonCount(1, 'referensi_masuk')
            ->assertJsonPath('referensi_masuk.0.kode', '02-2');
    }
}
