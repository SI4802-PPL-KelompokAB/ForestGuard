<?php

namespace Database\Factories;

use App\Models\CaseStatus;
use App\Models\ForestArea;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'INC-'.now()->format('Ym').'-'.strtoupper(Str::random(4)),
            'client_uuid' => (string) Str::uuid(),
            'reporter_id' => User::factory(),
            'incident_type_id' => IncidentType::query()->inRandomOrder()->value('id') ?? 1,
            'forest_area_id' => ForestArea::factory(),
            'case_status_id' => CaseStatus::query()->where('name', 'Reported')->value('id') ?? 1,
            'description' => fake()->paragraph(),
            'latitude' => fake()->latitude(-7.0, -6.0),
            'longitude' => fake()->longitude(106.0, 108.0),
            'gps_accuracy' => 5.0,
            'occurred_at' => now(),
        ];
    }
}
