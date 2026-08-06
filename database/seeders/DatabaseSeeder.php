<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            GrafologiKnowledgeSeeder::class,
            AdministratorSeeder::class,
            // Tambahkan seeder lain di sini seiring pengembangan
            // (mis. OrganizationSeeder saat Fase 06 HR dibangun)
        ]);
    }
}
