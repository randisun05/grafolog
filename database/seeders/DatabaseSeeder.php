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
            AdministratorSeeder::class,
            PricingPlanSeeder::class,
            ContentBlockSeeder::class,
        ]);
    }
}
