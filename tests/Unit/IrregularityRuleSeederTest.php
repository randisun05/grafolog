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

        $this->assertSame(28, IndikatorRule::count());
    }

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        $this->seed(IrregularityRuleSeeder::class);

        $this->assertSame(28, IndikatorRule::count());
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

    public function test_middle_zone_height_self_referential_indikator_intentionally_has_no_rule(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(IrregularityRuleSeeder::class);

        // "Middle zone height irregular/regular" was deliberately skipped -
        // ambiguous self-referential source data (kode 1 compared to itself).
        foreach (['26-6b', '27-5a', '36-8b', '38-3a', '11-1b'] as $kode) {
            $indikator = Indikator::where('kode', $kode)->first();
            if ($indikator) {
                $this->assertCount(0, $indikator->rules, "Indikator {$kode} should have no auto rule");
            }
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
    }
}
