<?php

namespace Database\Factories;

use App\Models\ForestArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ForestArea> */
class ForestAreaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->city(), 'area_ha' => 100, 'center_lat' => -6.8, 'center_lng' => 107.6];
    }
}
