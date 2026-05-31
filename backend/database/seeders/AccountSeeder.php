<?php

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
