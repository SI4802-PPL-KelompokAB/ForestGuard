<?php

namespace Database\Factories;

use App\Models\Checkpoint;
use App\Models\ForestArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Checkpoint> */
class CheckpointFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['forest_area_id' => ForestArea::factory(), 'code' => fake()->unique()->bothify('CP-########'), 'name' => fake()->streetName(), 'latitude' => -6.8, 'longitude' => 107.6];
    }
}
