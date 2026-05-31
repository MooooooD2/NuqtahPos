<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MakeFirstUserAdminCommand extends Command
{
    protected $signature = 'tenant:make-admin
                            {--tenant= : Tenant ID or code (omit to run on all tenants)}';

    protected $description = 'Give the first user of a tenant the admin role with all permissions';

    public function handle(): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenants found.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: <info>{$tenant->code}</info> ({$tenant->id})");

            tenancy()->initialize($tenant);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $user = User::oldest()->first();

            if (! $user) {
                $this->warn('  No users found — skipping.');
                tenancy()->end();

                continue;
            }

            $adminRole = Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'web'],
            );

            $allPerms = Permission::all();

            if ($allPerms->isEmpty()) {
                $this->warn('  No permissions in tenant DB — run tenants:seed first.');
                tenancy()->end();

                continue;
            }

            $adminRole->syncPermissions($allPerms);

            $user->update(['role' => 'admin', 'is_active' => true]);
            $user->syncRoles([$adminRole]);
            $user->givePermissionTo($allPerms);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->info("  ✓ {$user->username} → admin ({$allPerms->count()} permissions)");

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
