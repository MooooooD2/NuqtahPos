<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'phone' => $this->faker->numerify('05########'),
            'address' => $this->faker->address(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
