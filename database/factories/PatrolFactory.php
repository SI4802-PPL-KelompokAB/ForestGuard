<?php

namespace Database\Factories;

use App\Enums\PatrolStatus;
use App\Models\ForestArea;
use App\Models\Patrol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Patrol> */
class PatrolFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => 'P-'.Str::ulid(), 'client_uuid' => (string) Str::uuid(), 'ranger_id' => User::factory()->ranger(), 'created_by' => User::factory()->admin(), 'forest_area_id' => ForestArea::factory(), 'scheduled_at' => now('UTC'), 'created_at' => now('UTC'), 'status' => PatrolStatus::Assigned];
    }
}
