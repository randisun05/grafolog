<?php

namespace Tests\Unit;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use Database\Seeders\GrafologiKnowledgeSeeder;
use Database\Seeders\VariableEqualityRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariableEqualityRuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_rule_count_against_real_kb(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);

        $this->seed(VariableEqualityRuleSeeder::class);

        $this->assertSame(7 + 257, IndikatorRule::count());
    }

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(VariableEqualityRuleSeeder::class);

        $this->seed(VariableEqualityRuleSeeder::class);

        $this->assertSame(7 + 257, IndikatorRule::count());
    }

    public function test_middle_zone_width_equals_middle_zone_height(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(VariableEqualityRuleSeeder::class);

        $indikator = Indikator::where('kode', '11-1a')->firstOrFail();
        $rule = $indikator->rules->first();
        $mzOvalsWidth = MeasurementVariable::where('nama', 'M, Z, Ovals width')->firstOrFail();
        $mzh = MeasurementVariable::where('nama', 'Middle zone height')->firstOrFail();

        $this->assertSame('comparison', $rule->rule_type);
        $this->assertSame('equals', $rule->operator);
        $this->assertSame($mzOvalsWidth->id, $rule->variable_a_id);
        $this->assertSame($mzh->id, $rule->variable_b_id);
        $this->assertEquals(1.0, (float) $rule->koefisien);
    }

    public function test_ambiguous_alignment_and_score_comparison_indikator_have_no_rule(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(VariableEqualityRuleSeeder::class);

        // Deliberately skipped: sign-convention ambiguity (29-1a) and a
        // cross-Aspek SCORE comparison (38-9), not a measurement comparison
        // at all - unsupported by the current indikator_rules schema.
        foreach (['29-1a', '38-9'] as $kode) {
            $indikator = Indikator::where('kode', $kode)->first();
            if ($indikator) {
                $this->assertCount(0, $indikator->rules, "Indikator {$kode} should have no auto rule");
            }
        }
    }
}
