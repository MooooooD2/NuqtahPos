<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            SettingsSeeder::class,
            AccountSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            TenantSeeder::class,
            PlanSeeder::class,
            CurrencySeeder::class,
        ]);
    }
}
