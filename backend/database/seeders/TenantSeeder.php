<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Creates the master tenant in the central database.
 *
 * Run ONCE after `php artisan migrate`:
 *   php artisan db:seed --class=TenantSeeder
 *
 * This creates the tenant record, its database, and runs all migrations on it.
 * Then seed its users/data with:
 *   php artisan tenants:seed --tenants=<tenant-id>
 *
 * Finally, copy the tenant ID into .env as MASTER_TENANT_ID=<id>
 */
class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Only create if no tenants exist yet
        if (Tenant::count() > 0) {
            $this->command->info('Tenants already seeded – skipping.');

            return;
        }

        $tenant = Tenant::create([
            'name' => 'المتجر الرئيسي',
            'code' => 'main',
            'plan' => 'enterprise',
            'is_active' => true,
        ]);
        // CreateDatabase + MigrateDatabase event listeners fire automatically

        $this->command->info('✅ Master tenant created:');
        $this->command->info("   ID:   {$tenant->id}");
        $this->command->info("   Code: {$tenant->code}");
        $this->command->info("   DB:   tenant_{$tenant->id}");
        $this->command->newLine();
        $this->command->warn('👉 Add this to your .env file:');
        $this->command->warn("   MASTER_TENANT_ID={$tenant->id}");
        $this->command->newLine();
        $this->command->info("Then seed the master tenant's data:");
        $this->command->info("   php artisan tenants:seed --tenants={$tenant->id}");
    }
}
