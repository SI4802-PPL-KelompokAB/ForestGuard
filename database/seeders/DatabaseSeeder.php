<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CaseStatusSeeder::class,
            IncidentTypeSeeder::class,
            RiskLevelSeeder::class,
            ForestAreaSeeder::class,
            CheckpointSeeder::class,
            UserSeeder::class,
        ]);
    }
}
