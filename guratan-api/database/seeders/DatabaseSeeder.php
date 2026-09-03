<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GrafologiKnowledgeSeeder::class,
            IrregularityRuleSeeder::class,
            CategoryMatchRuleSeeder::class,
            VariableEqualityRuleSeeder::class,
            AdministratorSeeder::class,
            ProductSeeder::class,
            PricingPlanSeeder::class,
            ContentBlockSeeder::class,
        ]);
    }
}
