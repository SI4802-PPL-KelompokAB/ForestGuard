<?php

namespace Database\Seeders;

use App\Models\IncidentType;
use Illuminate\Database\Seeder;

class IncidentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Kebakaran hutan',
            'Dugaan illegal logging',
            'Ancaman satwa liar',
            'Degradasi lahan',
            'Perambahan',
            'Kerusakan lingkungan',
        ];

        foreach ($types as $name) {
            IncidentType::withTrashed()->updateOrCreate(
                ['name' => $name],
                ['deleted_at' => null]
            );
        }
    }
}
