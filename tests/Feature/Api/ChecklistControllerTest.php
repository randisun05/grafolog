<?php

namespace Tests\Feature\Api;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
use App\Models\MeasurementVariable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class ChecklistControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private function sample(User $grafolog, string $tier = 'comprehensive'): HandwritingSample
    {
        return HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => $tier,
            'status' => 'pending',
        ]);
    }

    private function indikatorFor(Aspek $aspek): Indikator
    {
        return Indikator::create([
            'kode' => "{$aspek->kode}-1", 'posisi' => 1, 'aspek_id' => $aspek->id, 'nama' => 'Indikator posisi 1',
        ]);
    }

    public function test_grafolog_can_view_checklist_for_own_sample(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $this->indikatorFor($aspek);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);

        $response = $this->actingAs($grafolog, 'sanctum')->getJson("/api/samples/{$sample->id}/checklist");

        $response->assertOk()->assertJsonPath('sindrom.0.aspek.0.kode', $aspek->kode);
    }

    public function test_non_owner_grafolog_cannot_view_checklist(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $this->indikatorFor($aspek);
        $grafologA = User::factory()->create(['role' => 'grafolog']);
        $grafologB = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafologA);

        $this->actingAs($grafologB, 'sanctum')->getJson("/api/samples/{$sample->id}/checklist")->assertForbidden();
    }

    public function test_toggle_manually_checks_an_indikator(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikatorFor($aspek);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/checklist/toggle", [
            'indikator_id' => $indikator->id, 'checked' => true,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('sample_indikator_checks', [
            'sample_id' => $sample->id, 'indikator_id' => $indikator->id, 'checked' => 1, 'sumber' => 'manual',
        ]);
        $this->assertDatabaseHas('audit_logs', ['aksi' => 'ubah_centang_indikator', 'actor_user_id' => $grafolog->id]);
    }

    public function test_toggle_rejects_completed_sample(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikatorFor($aspek);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);
        $sample->update(['status' => 'completed']);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/checklist/toggle", [
            'indikator_id' => $indikator->id, 'checked' => true,
        ])->assertStatus(422);
    }

    public function test_checklist_reflects_measurement_driven_auto_check_with_reason(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikatorFor($aspek);
        $variable = MeasurementVariable::create(['kode' => 'v1', 'axis' => 'vertical', 'nama' => 'Middle zone height']);
        $variable->kategori()->create(['kategori' => 'large', 'rentang' => '5-10', 'urutan' => 1]);
        $indikator->rules()->create([
            'rule_type' => 'category', 'variable_a_id' => $variable->id, 'category_label' => 'large',
        ]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);
        $sample->measurementReadings()->create(['variable_id' => $variable->id, 'nilai' => 7]);

        $response = $this->actingAs($grafolog, 'sanctum')->getJson("/api/samples/{$sample->id}/checklist");

        $response->assertOk()
            ->assertJsonPath('sindrom.0.aspek.0.indikator.0.checked', true)
            ->assertJsonPath('sindrom.0.aspek.0.indikator.0.sumber', 'auto');
        $this->assertStringContainsString('large', $response->json('sindrom.0.aspek.0.indikator.0.keterangan_pemicu'));
    }

    public function test_toggle_uncheck_with_cascade_requires_explicit_confirmation(): void
    {
        $sindrom = $this->seedMinimalAspek(2);
        $aspekList = Aspek::where('sindrom_id', $sindrom->id)->orderBy('id')->get();
        $source = $this->indikatorFor($aspekList[0]);
        $target = $this->indikatorFor($aspekList[1]);
        IndikatorCrossReference::create([
            'indikator_sumber_raw' => $source->kode, 'indikator_sumber_id' => $source->id,
            'mereferensikan_ke_kode' => $target->kode, 'match_status' => 'matched', 'aktif' => true,
        ]);
        $grafolog = User::factory()->create(['role' => 'grafolog']);
        $sample = $this->sample($grafolog);

        $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/checklist/toggle", [
            'indikator_id' => $source->id, 'checked' => true,
        ]);

        $response = $this->actingAs($grafolog, 'sanctum')->postJson("/api/samples/{$sample->id}/checklist/toggle", [
            'indikator_id' => $source->id, 'checked' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('cascade_candidates.0.id', $target->id);
        $this->assertDatabaseHas('sample_indikator_checks', ['indikator_id' => $target->id, 'checked' => 1]);
    }
}
