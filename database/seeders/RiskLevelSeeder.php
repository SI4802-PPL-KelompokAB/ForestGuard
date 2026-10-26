<?php

namespace Database\Seeders;

use App\Models\RiskLevel;
use Illuminate\Database\Seeder;

class RiskLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Rendah', 'sort_order' => 1, 'is_active' => true],
            ['name' => 'Sedang', 'sort_order' => 2, 'is_active' => true],
            ['name' => 'Tinggi', 'sort_order' => 3, 'is_active' => true],
            ['name' => 'Kritis', 'sort_order' => 4, 'is_active' => true],
        ];

        foreach ($levels as $level) {
            RiskLevel::query()->updateOrCreate(
                ['name' => $level['name']],
                [
                    'sort_order' => $level['sort_order'],
                    'is_active' => $level['is_active'],
                ]
            );
        }
    }
}
