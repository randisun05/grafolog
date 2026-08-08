<?php

namespace Tests\Unit\Services;

use App\Models\Aspek;
use App\Models\HandwritingSample;
use App\Models\Indikator;
use App\Models\IndikatorCrossReference;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use App\Models\SampleIndikatorCheck;
use App\Models\User;
use App\Services\Scoring\ChecklistEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsGrafologiKb;
use Tests\TestCase;

class ChecklistEngineServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsGrafologiKb;

    private ChecklistEngineService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(ChecklistEngineService::class);
    }

    private function sample(): HandwritingSample
    {
        $grafolog = User::factory()->create(['role' => 'grafolog']);

        return HandwritingSample::create([
            'user_id' => User::factory()->create()->id,
            'created_by' => $grafolog->id,
            'tier' => 'comprehensive',
            'status' => 'pending',
        ]);
    }

    private function variableWithCategories(string $nama = 'Middle zone height'): MeasurementVariable
    {
        $variable = MeasurementVariable::create(['kode' => $nama, 'axis' => 'vertical', 'nama' => $nama]);
        $variable->kategori()->create(['kategori' => 'large', 'rentang' => '5-10', 'urutan' => 1]);
        $variable->kategori()->create(['kategori' => 'small', 'rentang' => '0-2', 'urutan' => 2]);

        return $variable;
    }

    private function indikator(Aspek $aspek, int $posisi, ?string $varian = null): Indikator
    {
        $kode = "{$aspek->kode}-{$posisi}".($varian ?? '');

        return Indikator::create([
            'kode' => $kode, 'posisi' => $posisi, 'varian' => $varian,
            'aspek_id' => $aspek->id, 'nama' => "Indikator posisi $posisi",
        ]);
    }

    public function test_category_rule_auto_checks_when_measurement_matches(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $variable = $this->variableWithCategories();
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'category',
            'variable_a_id' => $variable->id, 'category_label' => 'large',
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $variable->id, 'nilai' => 7]);

        $this->engine->evaluateSample($sample);

        $this->assertDatabaseHas('sample_indikator_checks', [
            'sample_id' => $sample->id, 'indikator_id' => $indikator->id, 'sumber' => 'auto',
        ]);
        $check = SampleIndikatorCheck::where('sample_id', $sample->id)->first();
        $this->assertStringContainsString('large', $check->keterangan_pemicu);
    }

    public function test_category_rule_does_not_check_when_measurement_does_not_match(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $variable = $this->variableWithCategories();
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'category',
            'variable_a_id' => $variable->id, 'category_label' => 'large',
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $variable->id, 'nilai' => 1]);

        $this->engine->evaluateSample($sample);

        $this->assertDatabaseCount('sample_indikator_checks', 0);
    }

    public function test_indikator_without_measurement_data_stays_unresolved(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $variable = $this->variableWithCategories();
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'category',
            'variable_a_id' => $variable->id, 'category_label' => 'large',
        ]);

        $sample = $this->sample();
        // No measurement reading submitted at all.
        $this->engine->evaluateSample($sample);

        $this->assertDatabaseCount('sample_indikator_checks', 0);
    }

    public function test_comparison_rule_with_variable_b_and_koefisien(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $varA = MeasurementVariable::create(['kode' => 'd-stem', 'axis' => 'vertical', 'nama' => 'd stem width']);
        $varB = $this->variableWithCategories();
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'comparison',
            'variable_a_id' => $varA->id, 'operator' => 'greater_than',
            'koefisien' => 1.5, 'variable_b_id' => $varB->id,
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $varB->id, 'nilai' => 2]);
        $sample->measurementReadings()->create(['variable_id' => $varA->id, 'nilai' => 3.5]); // > 1.5 * 2 = 3

        $this->engine->evaluateSample($sample);

        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id]);
    }

    public function test_comparison_rule_with_fixed_compare_value(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $varA = MeasurementVariable::create(['kode' => 'margin-left', 'axis' => 'horizontal', 'nama' => 'Left margin']);
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'comparison',
            'variable_a_id' => $varA->id, 'operator' => 'less_than', 'compare_value' => 1.0,
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $varA->id, 'nilai' => 0.5]);

        $this->engine->evaluateSample($sample);

        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id]);
    }

    public function test_and_group_logic_requires_all_rules_true(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $indikator->update(['rule_group_logic' => 'AND']);
        $varA = $this->variableWithCategories('Var A');
        $varB = MeasurementVariable::create(['kode' => 'var-b', 'axis' => 'horizontal', 'nama' => 'Var B']);
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'category',
            'variable_a_id' => $varA->id, 'category_label' => 'large',
        ]);
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'comparison',
            'variable_a_id' => $varB->id, 'operator' => 'greater_than', 'compare_value' => 10,
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $varA->id, 'nilai' => 7]); // matches 'large'
        $sample->measurementReadings()->create(['variable_id' => $varB->id, 'nilai' => 1]); // fails > 10

        $this->engine->evaluateSample($sample);
        $this->assertDatabaseCount('sample_indikator_checks', 0);

        SampleIndikatorCheck::query()->delete();
        $sample->measurementReadings()->where('variable_id', $varB->id)->update(['nilai' => 20]);
        $this->engine->evaluateSample($sample);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id]);
    }

    public function test_or_group_logic_default_any_true_suffices(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1); // default OR
        $varA = $this->variableWithCategories('Var A');
        $varB = MeasurementVariable::create(['kode' => 'var-b', 'axis' => 'horizontal', 'nama' => 'Var B']);
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'category',
            'variable_a_id' => $varA->id, 'category_label' => 'large',
        ]);
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'comparison',
            'variable_a_id' => $varB->id, 'operator' => 'greater_than', 'compare_value' => 10,
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $varA->id, 'nilai' => 7]); // matches 'large'
        $sample->measurementReadings()->create(['variable_id' => $varB->id, 'nilai' => 1]); // fails > 10

        $this->engine->evaluateSample($sample);

        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id]);
    }

    public function test_reevaluation_does_not_overwrite_existing_manual_uncheck(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);
        $variable = $this->variableWithCategories();
        IndikatorRule::create([
            'indikator_id' => $indikator->id, 'rule_type' => 'category',
            'variable_a_id' => $variable->id, 'category_label' => 'large',
        ]);

        $sample = $this->sample();
        $sample->measurementReadings()->create(['variable_id' => $variable->id, 'nilai' => 7]);

        $this->engine->evaluateSample($sample);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id, 'checked' => 1]);

        // Grafolog explicitly disagrees and unchecks it via the toggle path.
        $this->engine->toggle($sample, $indikator->id, false);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id, 'checked' => 0]);

        // Re-running evaluation (e.g. after adding another measurement) must
        // NOT silently re-tick the indikator the grafolog already rejected -
        // the row stays, still checked=false.
        $this->engine->evaluateSample($sample);
        $this->assertDatabaseCount('sample_indikator_checks', 1);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $indikator->id, 'checked' => 0]);
    }

    public function test_checking_source_cascades_to_active_matched_cross_reference(): void
    {
        $sindrom = $this->seedMinimalAspek(2);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->orderBy('id')->get();
        $source = $this->indikator($aspek[0], 1);
        $target = $this->indikator($aspek[1], 1);
        IndikatorCrossReference::create([
            'indikator_sumber_raw' => $source->kode, 'indikator_sumber_id' => $source->id,
            'mereferensikan_ke_kode' => $target->kode, 'match_status' => 'matched', 'aktif' => true,
        ]);

        $sample = $this->sample();
        $this->engine->toggle($sample, $source->id, true);

        $this->assertDatabaseHas('sample_indikator_checks', [
            'sample_id' => $sample->id, 'indikator_id' => $target->id, 'sumber' => 'cascade',
        ]);
    }

    public function test_inactive_cross_reference_does_not_cascade(): void
    {
        $sindrom = $this->seedMinimalAspek(2);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->orderBy('id')->get();
        $source = $this->indikator($aspek[0], 1);
        $target = $this->indikator($aspek[1], 1);
        IndikatorCrossReference::create([
            'indikator_sumber_raw' => $source->kode, 'indikator_sumber_id' => $source->id,
            'mereferensikan_ke_kode' => $target->kode, 'match_status' => 'matched', 'aktif' => false,
        ]);

        $sample = $this->sample();
        $this->engine->toggle($sample, $source->id, true);

        $this->assertDatabaseMissing('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $target->id]);
    }

    public function test_unchecking_source_offers_confirmation_before_uncascading_target(): void
    {
        $sindrom = $this->seedMinimalAspek(2);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->orderBy('id')->get();
        $source = $this->indikator($aspek[0], 1);
        $target = $this->indikator($aspek[1], 1);
        IndikatorCrossReference::create([
            'indikator_sumber_raw' => $source->kode, 'indikator_sumber_id' => $source->id,
            'mereferensikan_ke_kode' => $target->kode, 'match_status' => 'matched', 'aktif' => true,
        ]);

        $sample = $this->sample();
        $this->engine->toggle($sample, $source->id, true);
        $this->assertDatabaseCount('sample_indikator_checks', 2);

        $result = $this->engine->toggle($sample, $source->id, false);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['requires_confirmation']);
        $this->assertSame([$target->id], array_column($result['cascade_candidates'], 'id'));
        // Nothing changed yet - both rows must survive, still checked, until
        // the grafolog explicitly decides.
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $source->id, 'checked' => 1]);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $target->id, 'checked' => 1]);

        $confirmed = $this->engine->toggle($sample, $source->id, false, [$target->id]);
        $this->assertTrue($confirmed['ok']);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $source->id, 'checked' => 0]);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $target->id, 'checked' => 0]);
    }

    public function test_unchecking_source_without_confirmation_leaves_target_checked(): void
    {
        $sindrom = $this->seedMinimalAspek(2);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->orderBy('id')->get();
        $source = $this->indikator($aspek[0], 1);
        $target = $this->indikator($aspek[1], 1);
        IndikatorCrossReference::create([
            'indikator_sumber_raw' => $source->kode, 'indikator_sumber_id' => $source->id,
            'mereferensikan_ke_kode' => $target->kode, 'match_status' => 'matched', 'aktif' => true,
        ]);

        $sample = $this->sample();
        $this->engine->toggle($sample, $source->id, true);

        // Grafolog is shown the confirmation prompt but the caller never
        // resubmits with alsoUncheckCascaded - i.e. they declined. Source
        // and target must both remain exactly as they were.
        $this->engine->toggle($sample, $source->id, false);

        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $source->id, 'checked' => 1]);
        $this->assertDatabaseHas('sample_indikator_checks', ['sample_id' => $sample->id, 'indikator_id' => $target->id, 'checked' => 1]);
    }

    public function test_tally_counts_distinct_posisi_and_ors_variants(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $posisi1a = $this->indikator($aspek, 1, 'a');
        $posisi1b = $this->indikator($aspek, 1, 'b');
        $posisi2 = $this->indikator($aspek, 2);
        $this->indikator($aspek, 3); // never checked

        $sample = $this->sample();
        $this->engine->toggle($sample, $posisi1a->id, true);
        $this->engine->toggle($sample, $posisi1b->id, true); // same posisi as above - OR'd, not double-counted
        $this->engine->toggle($sample, $posisi2->id, true);

        $tally = $this->engine->tallyPerAspek($sample);

        $this->assertSame(2, $tally[$aspek->kode]['posisi_tercentang']);
        $this->assertSame(2, $tally[$aspek->kode]['skor']);
        $this->assertSame(3, $tally[$aspek->kode]['total_posisi']);
    }

    public function test_tally_clamps_zero_checked_to_minimum_skor_of_one(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $this->indikator($aspek, 1);

        $sample = $this->sample();
        $tally = $this->engine->tallyPerAspek($sample);

        $this->assertSame(0, $tally[$aspek->kode]['posisi_tercentang']);
        $this->assertSame(1, $tally[$aspek->kode]['skor']);
    }

    public function test_checklist_for_groups_by_sindrom_and_aspek_with_tally(): void
    {
        $sindrom = $this->seedMinimalAspek(1);
        $aspek = Aspek::where('sindrom_id', $sindrom->id)->first();
        $indikator = $this->indikator($aspek, 1);

        $sample = $this->sample();
        $this->engine->toggle($sample, $indikator->id, true);

        $view = $this->engine->checklistFor($sample);

        $this->assertSame($sindrom->id, $view['sindrom'][0]['id']);
        $this->assertSame($aspek->kode, $view['sindrom'][0]['aspek'][0]['kode']);
        $this->assertSame(1, $view['sindrom'][0]['aspek'][0]['skor']);
        $this->assertTrue($view['sindrom'][0]['aspek'][0]['indikator'][0]['checked']);
        $this->assertSame('manual', $view['sindrom'][0]['aspek'][0]['indikator'][0]['sumber']);
    }
}
