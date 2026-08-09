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

        $this->assertSame(66, IndikatorRule::count());
    }

    public function test_running_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(CategoryMatchRuleSeeder::class);

        $this->seed(CategoryMatchRuleSeeder::class);

        $this->assertSame(66, IndikatorRule::count());
    }

    public function test_middle_zone_height_large_matches_the_real_category(): void
    {
        $this->seed(GrafologiKnowledgeSeeder::class);
        $this->seed(CategoryMatchRuleSeeder::class);

        $indikator = Indikator::where('kode', '02-8a')->firstOrFail();
        $rule = $indikator->rules->first();
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
        foreach (['02-8a', '04-5a', '11-2b', '23-4a', '35-8-dup3'] as $kode) {
            $indikator = Indikator::where('kode', $kode)->firstOrFail();
            $this->assertCount(1, $indikator->rules, "Indikator {$kode} should have exactly 1 rule");
            $this->assertSame('large', $indikator->rules->first()->category_label);
        }
    }
}
