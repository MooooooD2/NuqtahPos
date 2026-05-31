<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 10);
        $price = fake()->randomFloat(2, 10, 500);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => Product::factory(),
            'product_name' => fake()->words(3, true),
            'quantity' => $qty,
            'price' => $price,
            'subtotal' => $qty * $price,
        ];
    }
}
