<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'price' => fake()->randomFloat(2, 5, 500),
            'cost_price' => fake()->randomFloat(2, 1, 200),
            'quantity' => fake()->numberBetween(0, 100),
            'min_stock' => fake()->numberBetween(1, 10),
            'barcode' => fake()->unique()->ean13(),
            'category' => fake()->word(),
        ];
    }
}
