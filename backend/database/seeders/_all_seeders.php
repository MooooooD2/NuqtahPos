<?php

// =============================================================
// DATABASE SEEDERS - البيانات الافتراضية
// =============================================================

// ---------------------------------------------------------------
// FILE: database/seeders/DatabaseSeeder.php
// ---------------------------------------------------------------

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            AccountSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
        ]);
    }
}

// ---------------------------------------------------------------
// FILE: database/seeders/UserSeeder.php
// ---------------------------------------------------------------

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin - المدير
        User::firstOrCreate(['username' => 'admin'], [
            'password' => Hash::make('admin123'),
            'full_name' => 'المدير العام',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Cashier - الكاشير
        User::firstOrCreate(['username' => 'cashier'], [
            'password' => Hash::make('cashier123'),
            'full_name' => 'أمين الصندوق',
            'role' => 'cashier',
            'is_active' => true,
        ]);

        // Warehouse - مسؤول المخزن
        User::firstOrCreate(['username' => 'warehouse'], [
            'password' => Hash::make('warehouse123'),
            'full_name' => 'مسؤول المخزن',
            'role' => 'warehouse',
            'is_active' => true,
        ]);
    }
}

// ---------------------------------------------------------------
// FILE: database/seeders/AccountSeeder.php
// ---------------------------------------------------------------

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets - الأصول
            ['1000', 'الأصول / Assets',              'asset',     null,  'Main asset accounts'],
            ['1100', 'النقدية / Cash',               'asset',     1,     'Cash on hand'],
            ['1200', 'البنوك / Banks',               'asset',     1,     'Bank accounts'],
            ['1300', 'المخزون / Inventory',          'asset',     1,     'Inventory value'],
            ['1400', 'أصول ثابتة / Fixed Assets',   'asset',     1,     'Fixed assets'],

            // Liabilities - الخصوم
            ['2000', 'الخصوم / Liabilities',         'liability', null,  'Main liability accounts'],
            ['2100', 'حسابات موردين / Payables',     'liability', 6,     'Supplier accounts payable'],
            ['2200', 'قروض / Loans',                 'liability', 6,     'Long-term loans'],

            // Equity - حقوق الملكية
            ['3000', 'حقوق الملكية / Equity',        'equity',    null,  'Owners equity'],
            ['3100', 'رأس المال / Capital',           'equity',    9,     'Paid capital'],
            ['3200', 'أرباح محتجزة / Retained Earnings', 'equity',  9,    'Retained earnings'],

            // Revenue - الإيرادات
            ['4000', 'الإيرادات / Revenue',           'revenue',   null,  'Revenue accounts'],
            ['4100', 'مبيعات / Sales',                'revenue',   12,    'Sales revenue'],
            ['4200', 'إيرادات أخرى / Other Income',  'revenue',   12,    'Other income'],

            // Expenses - المصروفات
            ['5000', 'المصروفات / Expenses',          'expense',   null,  'Expense accounts'],
            ['5100', 'تكلفة البضاعة / COGS',          'expense',   15,    'Cost of goods sold'],
            ['5200', 'مرتبات / Salaries',             'expense',   15,    'Employee salaries'],
            ['5300', 'إيجار / Rent',                  'expense',   15,    'Rent expense'],
            ['5400', 'مرافق / Utilities',             'expense',   15,    'Utilities expense'],
            ['5500', 'مصروفات أخرى / Other Expenses', 'expense',   15,    'Other operating expenses'],
        ];

        foreach ($accounts as $acc) {
            Account::firstOrCreate(['account_code' => $acc[0]], [
                'account_name' => $acc[1],
                'account_type' => $acc[2],
                'parent_id' => $acc[3],
                'description' => $acc[4],
                'balance' => 0,
            ]);
        }
    }
}

// ---------------------------------------------------------------
// FILE: database/seeders/SupplierSeeder.php
// ---------------------------------------------------------------

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'شركة النور للأغذية',   'phone' => '01000000001', 'address' => 'القاهرة',    'email' => 'nour@company.com'],
            ['name' => 'شركة السكر المصرية',   'phone' => '01000000002', 'address' => 'الجيزة',    'email' => 'sugar@company.com'],
            ['name' => 'شركة الزيوت العربية',  'phone' => '01000000003', 'address' => 'الإسكندرية', 'email' => 'oils@company.com'],
            ['name' => 'شركة النظافة والتنظيف', 'phone' => '01000000004', 'address' => 'القاهرة',    'email' => 'clean@company.com'],
        ];

        foreach ($suppliers as $s) {
            Supplier::firstOrCreate(['name' => $s['name']], $s);
        }
    }
}

// ---------------------------------------------------------------
// FILE: database/seeders/ProductSeeder.php
// ---------------------------------------------------------------

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['أرز 1 كيلو',       25.0, 18.0, 100, 10, '123456789001', 'أغذية',     'شركة النور'],
            ['سكر 1 كيلو',       15.0, 10.0, 150, 15, '123456789002', 'أغذية',     'شركة السكر'],
            ['زيت ذرة 1 لتر',    35.0, 25.0,  80,  8, '123456789003', 'أغذية',     'شركة الزيوت'],
            ['صابون سائل 500مل', 12.0,  7.0, 200, 20, '123456789004', 'منظفات',    'شركة النظافة'],
            ['شامبو 250 مل',     45.0, 30.0,  60,  6, '123456789005', 'مستحضرات',  'شركة التجميل'],
            ['مكرونة 400 جرام',  8.50,  5.0, 300, 30, '123456789006', 'أغذية',     'شركة النور'],
            ['عصير برتقال 1 لتر', 18.0, 12.0,  90,  9, '123456789007', 'مشروبات',   'شركة النور'],
            ['مياه معدنية 1.5 لتر', 3.0, 1.5, 500, 50, '123456789008', 'مشروبات',   'شركة المياه'],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['barcode' => $p[5]], [
                'name' => $p[0],
                'price' => $p[1],
                'cost_price' => $p[2],
                'quantity' => $p[3],
                'min_stock' => $p[4],
                'barcode' => $p[5],
                'category' => $p[6],
                'supplier' => $p[7],
            ]);
        }
    }
}
