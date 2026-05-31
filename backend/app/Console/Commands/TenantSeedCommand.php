<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Throwable;

class TenantSeedCommand extends Command
{
    protected $signature = 'tenant:seed
                            {--tenant= : Tenant ID or code (omit to run on all tenants)}
                            {--class= : Specific seeder class name (default: DatabaseSeeder)}';

    protected $description = 'Run DatabaseSeeder inside a tenant database';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenants found.');

            return self::FAILURE;
        }

        $seederClass = $this->option('class')
            ? 'Database\\Seeders\\' . $this->option('class')
            : DatabaseSeeder::class;

        foreach ($tenants as $tenant) {
            $this->line("Seeding tenant: <info>{$tenant->code}</info> ({$tenant->id})");

            tenancy()->initialize($tenant);

            try {
                app($seederClass)->setCommand($this)->run();
                $this->info('  ✓ Done');
            } catch (Throwable $e) {
                $this->error("  ✗ {$e->getMessage()}");
                tenancy()->end();

                return self::FAILURE;
            }

            tenancy()->end();
        }

        return self::SUCCESS;
    }

    private function resolveTenants()
    {
        $filter = $this->option('tenant');

        if (! $filter) {
            return Tenant::all();
        }

        return Tenant::where('id', $filter)
            ->orWhere('code', $filter)
            ->get();
    }
}
