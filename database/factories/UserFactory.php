<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        $first = $this->faker->firstName();
        $last  = $this->faker->lastName();

        return [
            'first_name' => $first,
            'last_name'  => $last,
            'name'       => "{$first} {$last}",
            'username'   => Str::lower(Str::random(12)),
            'email'      => $this->faker->unique()->safeEmail(),
            'password'   => static::$password ??= Hash::make('password'),
            'role'       => 'gate_officer',
            'status'     => 'active',
        ];
    }

    /** A super-user that bypasses RBAC. */
    public function systemAdmin(): static
    {
        return $this->state(fn () => ['role' => 'system_administrator']);
    }

    /** Assign a specific primary role string. */
    public function role(string $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
