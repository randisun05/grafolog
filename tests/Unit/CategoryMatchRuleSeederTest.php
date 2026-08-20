<?php

namespace Tests\Unit;

use App\Models\Indikator;
use App\Models\IndikatorRule;
use App\Models\MeasurementVariable;
use Database\Seeders\CategoryMatchRuleSeeder;
use Database\Seeders\GrafologiKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryMatchRuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_rule_count_against_real_kb(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);

        $this->seed(CategoryMatchRuleSeeder::class);

        $this->assertSame(66 + 257, IndikatorRule::count());
    }

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(CategoryMatchRuleSeeder::class);

        $this->seed(CategoryMatchRuleSeeder::class);

        $this->assertSame(66 + 257, IndikatorRule::count());
    }

    public function test_middle_zone_height_large_matches_the_real_category(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(CategoryMatchRuleSeeder::class);

        $indikator = Indikator::where('kode', '02-8a')->firstOrFail();
        $rule = $indikator->rules->firstWhere('rule_type', 'category');
        $mzh = MeasurementVariable::where('nama', 'Middle zone height')->firstOrFail();

        $this->assertSame('category', $rule->rule_type);
        $this->assertSame($mzh->id, $rule->variable_a_id);
        $this->assertSame('large', $rule->category_label);
    }

    public function test_same_named_indikator_across_multiple_aspek_all_get_the_rule(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(CategoryMatchRuleSeeder::class);

        // "Middle zone height large" appears in 5 different Aspek - all
        // should get the same category rule, this is intentional (one
        // physical trait is evidence for multiple personality traits).
        // Filtered to rule_type='category' - some of these Indikator may
        // ALSO carry an unrelated indikator_checked rule from cross-
        // reference migration (2026-08-19), that's expected to coexist.
        foreach (['02-8a', '04-5a', '11-2b', '23-4a', '35-8-dup3'] as $kode) {
            $indikator = Indikator::where('kode', $kode)->firstOrFail();
            $categoryRules = $indikator->rules->where('rule_type', 'category');
            $this->assertCount(1, $categoryRules, "Indikator {$kode} should have exactly 1 category rule");
            $this->assertSame('large', $categoryRules->first()->category_label);
        }
    }
}
