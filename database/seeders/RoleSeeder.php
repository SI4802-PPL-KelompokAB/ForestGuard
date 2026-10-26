<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Role::ADMIN, Role::RANGER, Role::CITIZEN] as $name) {
            Role::query()->firstOrCreate(['name' => $name]);
        }
    }
}
