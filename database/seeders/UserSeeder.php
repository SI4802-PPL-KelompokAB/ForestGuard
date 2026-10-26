<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const DEV_PASSWORD = 'Password123!';

    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'name');

        $users = [
            [
                'email' => 'admin@forestguard.test',
                'name' => 'Admin Hutan',
                'role' => Role::ADMIN,
            ],
            [
                'email' => 'ranger1@forestguard.test',
                'name' => 'Ranger Satu',
                'role' => Role::RANGER,
            ],
            [
                'email' => 'ranger2@forestguard.test',
                'name' => 'Ranger Dua',
                'role' => Role::RANGER,
            ],
            [
                'email' => 'citizen1@forestguard.test',
                'name' => 'Warga Satu',
                'role' => Role::CITIZEN,
            ],
            [
                'email' => 'citizen2@forestguard.test',
                'name' => 'Warga Dua',
                'role' => Role::CITIZEN,
            ],
        ];

        foreach ($users as $user) {
            $model = User::withTrashed()->firstOrNew(['email' => $user['email']]);
            $model->name = $user['name'];
            $model->password = self::DEV_PASSWORD;
            $model->role_id = $roles[$user['role']];
            $model->is_active = true;
            $model->deleted_at = null;
            $model->save();
        }
    }
}
