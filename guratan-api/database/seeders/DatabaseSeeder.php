<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GrafologiKnowledgeSeeder::class,
            TerjemahanIdSeeder::class,
            IrregularityRuleSeeder::class,
            CategoryMatchRuleSeeder::class,
            VariableEqualityRuleSeeder::class,
            AdministratorSeeder::class,
            PricingPlanSeeder::class,
            ProductSeeder::class,
            ContentBlockSeeder::class,
            SelfCheckSeeder::class, // setelah KB: melengkapi pemetaan opsi -> Indikator
            ContentSeeder::class,
        ]);
    }
}
