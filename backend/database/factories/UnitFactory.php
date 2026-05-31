<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        $units = [
            ['name' => 'كيلوغرام', 'abbreviation' => 'كجم'],
            ['name' => 'غرام',     'abbreviation' => 'جم'],
            ['name' => 'لتر',      'abbreviation' => 'لتر'],
            ['name' => 'قطعة',     'abbreviation' => 'قطعة'],
            ['name' => 'صندوق',    'abbreviation' => 'صند'],
        ];

        $pick = fake()->unique()->randomElement($units);

        return [
            'name' => $pick['name'],
            'abbreviation' => $pick['abbreviation'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
