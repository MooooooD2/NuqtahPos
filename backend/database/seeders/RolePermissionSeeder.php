<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            // ── Dashboard ──────────────────────────────────────────────────────
            'view_dashboard',

            // ── POS ────────────────────────────────────────────────────────────
            'view_pos',
            'create_invoice',
            'search_products',

            // ── Returns ────────────────────────────────────────────────────────
            'view_returns',
            'create_return',

            // ── Warehouse — products ───────────────────────────────────────────
            'view_warehouse',
            'add_product',
            'edit_product',
            'delete_product',
            'add_stock',

            // ── Warehouse — suppliers ──────────────────────────────────────────
            'view_suppliers',
            'add_supplier',
            'edit_supplier',
            'delete_supplier',

            // ── Warehouse — purchase orders ────────────────────────────────────
            'view_purchase_orders',
            'create_purchase_order',
            'approve_purchase_order',
            'receive_purchase_order',

            // ── Warehouse — supplier payments & accounts ───────────────────────
            'view_supplier_payments',
            'create_supplier_payment',

            // ── Accounting ────────────────────────────────────────────────────
            'view_accounting',
            'manage_accounts',
            'create_journal_entry',

            // ── Reports ───────────────────────────────────────────────────────
            'view_reports',
            'view_financial_reports',

            // ── Settings ──────────────────────────────────────────────────────
            'view_settings',
            'update_settings',
            'manage_settings',   // broad settings gate (HR, white-label, currencies …)

            // ── User & role management ────────────────────────────────────────
            'manage_roles',
            'manage_permissions',

            // ── Tenant management (master-tenant only) ────────────────────────
            'manage_tenants',

            // ── HR Module ─────────────────────────────────────────────────────
            'view_hr',           // read-only access to HR pages
            'manage_hr',         // full HR management (payroll generation, edit attendance…)

            // ── Shift Management ──────────────────────────────────────────────
            'view_shifts',       // see all employees' shifts (admin panel)
            'manage_shifts',     // assign, edit, delete shifts

            // ── CRM ───────────────────────────────────────────────────────────
            'view_crm',
            'manage_crm',

            // ── Kitchen Display ───────────────────────────────────────────────
            'view_kitchen',

            // ── QR Ordering ───────────────────────────────────────────────────
            'view_qr_orders',
            'manage_qr_orders',

            // ── Kiosk ─────────────────────────────────────────────────────────
            'view_kiosk',

            // ── Cashback & Promotions ─────────────────────────────────────────
            'manage_cashback',
            'manage_promotions',

            // ── Franchise ────────────────────────────────────────────────────
            'view_franchise',

            // ── Currencies ───────────────────────────────────────────────────
            'manage_currencies',

            // ── Pricing & Dynamic Pricing ────────────────────────────────────
            'manage_pricing_rules',
            'manage_dynamic_pricing',

            // ── White Label ───────────────────────────────────────────────────
            'manage_white_label',

            // ── Waste & Recipes ───────────────────────────────────────────────
            'manage_waste',
            'manage_recipes',

            // ── Device Sessions ───────────────────────────────────────────────
            'manage_device_sessions',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // ── Roles ─────────────────────────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'admin',     'guard_name' => $guard]);
        $manager = Role::firstOrCreate(['name' => 'manager',   'guard_name' => $guard]);
        $warehouse = Role::firstOrCreate(['name' => 'warehouse',  'guard_name' => $guard]);
        $cashier = Role::firstOrCreate(['name' => 'cashier',   'guard_name' => $guard]);
        $hr = Role::firstOrCreate(['name' => 'hr',        'guard_name' => $guard]);

        // Admin gets everything except manage_tenants (master tenant only)
        $isMaster = config('tenancy.master_tenant') &&
                    tenancy()->tenant?->id === config('tenancy.master_tenant');

        $adminPerms = Permission::where('name', '!=', 'manage_tenants')->pluck('name')->toArray();
        if ($isMaster) {
            $adminPerms = Permission::pluck('name')->toArray();
        }
        $admin->syncPermissions($adminPerms);

        // Manager: everything except tenant admin, white-label, and tenants management
        $manager->syncPermissions([
            'view_dashboard',
            'view_pos', 'create_invoice', 'search_products',
            'view_returns', 'create_return',
            'view_warehouse', 'add_product', 'edit_product', 'delete_product', 'add_stock',
            'view_suppliers', 'add_supplier', 'edit_supplier', 'delete_supplier',
            'view_purchase_orders', 'create_purchase_order', 'approve_purchase_order', 'receive_purchase_order',
            'view_supplier_payments', 'create_supplier_payment',
            'view_accounting', 'manage_accounts', 'create_journal_entry',
            'view_reports', 'view_financial_reports',
            'view_settings', 'update_settings',
            'manage_roles',
            'view_hr', 'manage_hr',
            'view_shifts', 'manage_shifts',
            'view_crm', 'manage_crm',
            'view_kitchen',
            'view_qr_orders', 'manage_qr_orders',
            'view_kiosk',
            'manage_cashback', 'manage_promotions',
            'view_franchise',
            'manage_currencies',
            'manage_pricing_rules', 'manage_dynamic_pricing',
            'manage_waste', 'manage_recipes',
            'manage_device_sessions',
        ]);

        // Warehouse: full inventory + purchasing + suppliers + POS (no HR, accounting, settings)
        $warehouse->syncPermissions([
            'view_dashboard',
            'view_pos', 'create_invoice', 'search_products',
            'view_returns', 'create_return',
            'view_warehouse', 'add_product', 'edit_product', 'delete_product', 'add_stock',
            'view_suppliers', 'add_supplier', 'edit_supplier', 'delete_supplier',
            'view_purchase_orders', 'create_purchase_order', 'receive_purchase_order',
            'view_supplier_payments', 'create_supplier_payment',
            'view_reports',
            'manage_waste', 'manage_recipes',
        ]);

        // Cashier: POS, returns, kitchen, kiosk, QR
        $cashier->syncPermissions([
            'view_dashboard',
            'view_pos', 'create_invoice', 'search_products',
            'view_returns', 'create_return',
            'view_kitchen',
            'view_qr_orders',
            'view_kiosk',
        ]);

        // HR role: only HR module + attendance
        $hr->syncPermissions([
            'view_dashboard',
            'view_hr', 'manage_hr',
            'view_shifts', 'manage_shifts',
        ]);
    }
}
