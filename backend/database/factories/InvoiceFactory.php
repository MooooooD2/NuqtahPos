<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 50, 2000);
        $user = User::factory()->create();

        return [
            'invoice_number' => 'INV-' . fake()->unique()->numerify('########'),
            'total' => $total,
            'discount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'final_total' => $total,
            'payment_method' => 'cash',
            'cashier_id' => $user->id,
            'cashier_name' => $user->full_name,
            'status' => 'completed',
            'date' => now(),
        ];
    }
}
