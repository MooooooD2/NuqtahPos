<?php

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
