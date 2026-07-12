<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Container>
 */
class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition(): array
    {
        return [
            'container_no' => strtoupper($this->faker->unique()->bothify('????#######')),
            'size'         => '40',
            'type_code'    => 'HC',
            'customer_id'  => Customer::factory(),
            'condition'    => 'sound',
            'cargo_status' => 'empty',
            'status'       => 'in_yard',
            'gate_in_date' => now()->subDays(3)->toDateString(),
        ];
    }

    public function inRepair(): static
    {
        return $this->state(fn () => ['status' => 'in_repair', 'condition' => 'require_repair']);
    }

    public function reserved(): static
    {
        return $this->state(fn () => ['status' => 'reserved']);
    }

    public function released(): static
    {
        return $this->state(fn () => ['status' => 'released']);
    }
}
