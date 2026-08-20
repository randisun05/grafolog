<?php

namespace Tests\Unit;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use Database\Seeders\GrafologiKnowledgeSeeder;
use Database\Seeders\IrregularityRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IrregularityRuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_rule_count_against_real_kb(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);

        $this->seed(IrregularityRuleSeeder::class);

        $this->assertSame(33 + 257, IndikatorRule::count());
    }

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        $this->seed(IrregularityRuleSeeder::class);

        $this->assertSame(33 + 257, IndikatorRule::count());
    }

    public function test_extension_spacing_irregular_uses_or_logic_with_two_rules(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        $indikator = Indikator::where('kode', '27-2')->firstOrFail();

        $this->assertSame('OR', $indikator->rule_group_logic);
        $this->assertCount(2, $indikator->rules);
    }

    public function test_extension_spacing_regular_uses_and_logic_with_two_rules(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        $indikator = Indikator::where('kode', '14-1d')->firstOrFail();

        $this->assertSame('AND', $indikator->rule_group_logic);
        $this->assertCount(2, $indikator->rules);
    }

    public function test_middle_zone_height_self_comparison_uses_range_vs_point_value(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        // Retrofit 2026-08-17: "Range is more than 1x Middle zone height" IS
        // coherent, not a source typo - variable_a in range mode (selisih
        // maks-min) compared to variable_b (MZH) in point mode. Previously
        // skipped as ambiguous; now has exactly 1 rule each.
        // Filtered to rule_type='comparison' - some of these Indikator may
        // ALSO carry an unrelated indikator_checked rule from cross-
        // reference migration (2026-08-19), that's expected to coexist.
        $mzh = MeasurementVariable::where('nama', 'Middle zone height')->firstOrFail();
        foreach (['26-6b' => 'greater_than', '27-5a' => 'greater_than', '36-8b' => 'greater_than', '38-3a' => 'greater_than', '11-1b' => 'less_or_equal'] as $kode => $operator) {
            $indikator = Indikator::where('kode', $kode)->firstOrFail();
            $comparisonRules = $indikator->rules->where('rule_type', 'comparison');
            $rule = $comparisonRules->first();
            $this->assertCount(1, $comparisonRules, "Indikator {$kode} should have exactly 1 comparison rule");
            $this->assertSame('range', $rule->variable_a_value_mode);
            $this->assertSame($mzh->id, $rule->variable_a_id);
            $this->assertSame($mzh->id, $rule->variable_b_id);
            $this->assertSame($operator, $rule->operator);
        }
    }

    public function test_ovals_height_irregular_matches_the_given_ratio(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        $indikator = Indikator::where('kode', '27-5b')->firstOrFail();
        $rule = $indikator->rules->first();
        $mzh = MeasurementVariable::where('nama', 'Middle zone height')->firstOrFail();

        $this->assertSame('comparison', $rule->rule_type);
        $this->assertSame('greater_than', $rule->operator);
        $this->assertEquals(1.0, (float) $rule->koefisien);
        $this->assertSame($mzh->id, $rule->variable_b_id);
        $this->assertSame('range', $rule->variable_a_value_mode);
    }
}
