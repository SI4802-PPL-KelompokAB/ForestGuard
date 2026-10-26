<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::query()->where('name', Role::CITIZEN)->value('id'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= 'Password123!',
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->where('name', Role::ADMIN)->value('id'),
        ]);
    }

    public function ranger(): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->where('name', Role::RANGER)->value('id'),
        ]);
    }

    public function citizen(): static
    {
        return $this->state(fn (): array => [
            'role_id' => Role::query()->where('name', Role::CITIZEN)->value('id'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
