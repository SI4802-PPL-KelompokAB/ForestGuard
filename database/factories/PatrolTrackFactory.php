<?php

namespace Database\Factories;

use App\Models\Patrol;
use App\Models\PatrolTrack;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PatrolTrack> */
class PatrolTrackFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'patrol_id' => Patrol::factory(),
            'latitude' => fake()->latitude(-7.5, -6.5),
            'longitude' => fake()->longitude(106.5, 108.5),
            'recorded_at' => now('UTC'),
        ];
    }
}
