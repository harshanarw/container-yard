<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'code'          => strtoupper(Str::random(6)),
            'name'          => $this->faker->company(),
            'type'          => 'shipping_line',
            'currency'      => 'LKR',
            'payment_terms' => 'net30',
            'status'        => 'active',
        ];
    }

    public function taxExempt(): static
    {
        return $this->state(fn () => ['tax_exempt' => true]);
    }
}
