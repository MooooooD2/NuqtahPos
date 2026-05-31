<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        // Use uniqid to guarantee no collision across test runs even when
        // the tenant DB is not fully cleaned between test classes.
        return [
            'username' => 'u_' . uniqid('', true),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'full_name' => fake()->name(),
            'role' => fake()->randomElement(['admin', 'cashier', 'warehouse']),
            'is_active' => true,
            'language' => 'ar',
        ];
    }
}
