<?php

namespace Database\Seeders;

use App\Models\CaseStatus;
use Illuminate\Database\Seeder;

class CaseStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Reported', 'sequence' => 1, 'is_terminal' => false],
            ['name' => 'Under Verification', 'sequence' => 2, 'is_terminal' => false],
            ['name' => 'Verified', 'sequence' => 3, 'is_terminal' => false],
            ['name' => 'Assigned', 'sequence' => 4, 'is_terminal' => false],
            ['name' => 'Investigation/Response', 'sequence' => 5, 'is_terminal' => false],
            ['name' => 'Monitoring', 'sequence' => 6, 'is_terminal' => false],
            ['name' => 'Resolved', 'sequence' => 7, 'is_terminal' => true],
            ['name' => 'Rejected', 'sequence' => 8, 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            CaseStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                [
                    'sequence' => $status['sequence'],
                    'is_terminal' => $status['is_terminal'],
                ]
            );
        }
    }
}
