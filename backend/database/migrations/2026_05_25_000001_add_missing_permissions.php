<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Ensure all new permissions introduced in the Phase 10 + 11 roadmap exist.
 * Safe to run multiple times (firstOrCreate).
 */
return new class extends Migration
{
    /** New permissions added since the original seeder was written. */
    private const NEW_PERMISSIONS = [
        // HR
        'view_hr',
        'manage_hr',
        // Shifts
        'view_shifts',
        'manage_shifts',
        // CRM
        'view_crm',
        'manage_crm',
        // Front-of-house
        'view_kitchen',
        'view_qr_orders',
        'manage_qr_orders',
        'view_kiosk',
        // Marketing
        'manage_cashback',
        'manage_promotions',
        // Finance / config
        'view_franchise',
        'manage_currencies',
        'manage_pricing_rules',
        'manage_dynamic_pricing',
        'manage_white_label',
        // Operations
        'manage_waste',
        'manage_recipes',
        'manage_device_sessions',
        // Ensure broad settings perm exists
        'manage_settings',
    ];

    public function up(): void
    {
        // Spatie uses its own schema — just ensure the permission rows exist.
        foreach (self::NEW_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Grant all new permissions to the admin role of every tenant
        // so existing tenants don't have to re-seed.
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
        if ($adminRole) {
            $existing = $adminRole->permissions->pluck('name')->toArray();
            $toAdd    = array_diff(self::NEW_PERMISSIONS, $existing);
            if ($toAdd) {
                $adminRole->givePermissionTo($toAdd);
            }
        }
    }

    public function down(): void
    {
        // Remove only if no role has them (safe rollback)
        foreach (self::NEW_PERMISSIONS as $name) {
            $perm = Permission::where('name', $name)->first();
            $perm?->delete();
        }
    }
};
