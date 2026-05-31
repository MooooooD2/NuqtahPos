<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            // individuals — code format matches CustomerService::nextCode() → CUST-XXXX
            ['code' => 'CUST-0001', 'type' => 'individual', 'name' => 'أحمد محمد علي',      'phone' => '01012345678', 'email' => null,                    'governate' => 'القاهرة',    'city' => 'مدينة نصر',   'credit_limit' => 500,  'is_active' => true],
            ['code' => 'CUST-0002', 'type' => 'individual', 'name' => 'فاطمة إبراهيم',       'phone' => '01123456789', 'email' => 'fatma@example.com',      'governate' => 'الجيزة',     'city' => 'الدقي',        'credit_limit' => 300,  'is_active' => true],
            ['code' => 'CUST-0003', 'type' => 'individual', 'name' => 'محمود حسن سالم',      'phone' => '01234567890', 'email' => null,                    'governate' => 'الإسكندرية', 'city' => 'المنتزه',      'credit_limit' => 200,  'is_active' => true],
            ['code' => 'CUST-0004', 'type' => 'individual', 'name' => 'نورهان طارق',         'phone' => '01511223344', 'email' => null,                    'governate' => 'القاهرة',    'city' => 'شبرا',         'credit_limit' => 0,    'is_active' => true],
            ['code' => 'CUST-0005', 'type' => 'individual', 'name' => 'خالد عبد الرحمن',     'phone' => '01098765432', 'email' => 'khaled@mail.com',        'governate' => 'القاهرة',    'city' => 'المعادي',      'credit_limit' => 1000, 'is_active' => true],
            ['code' => 'CUST-0006', 'type' => 'individual', 'name' => 'سمر يوسف',            'phone' => '01265432198', 'email' => null,                    'governate' => 'الغربية',    'city' => 'طنطا',         'credit_limit' => 0,    'is_active' => false],

            // businesses
            ['code' => 'CUST-0007', 'type' => 'business', 'name' => 'شركة النجم للتجارة',          'phone' => '01000111222', 'email' => 'star@company.eg',  'governate' => 'القاهرة',    'city' => 'مصر الجديدة',  'credit_limit' => 5000, 'is_active' => true,
                'tax_number' => '123456789', 'commercial_register' => '987654'],
            ['code' => 'CUST-0008', 'type' => 'business', 'name' => 'مؤسسة الرشيد',                'phone' => '01000333444', 'email' => null,               'governate' => 'الجيزة',     'city' => 'أكتوبر',       'credit_limit' => 3000, 'is_active' => true,
                'tax_number' => '987654321', 'commercial_register' => '123456'],
            ['code' => 'CUST-0009', 'type' => 'business', 'name' => 'مجموعة هلال للمواد الغذائية', 'phone' => '01000555666', 'email' => 'hilal@food.eg',    'governate' => 'الإسكندرية', 'city' => 'العجمي',       'credit_limit' => 8000, 'is_active' => true,
                'tax_number' => '555444333', 'commercial_register' => '111222'],
            ['code' => 'CUST-0010', 'type' => 'individual', 'name' => 'عمرو سعيد الحسيني',   'phone' => '01155667788', 'email' => null,                    'governate' => 'المنوفية',   'city' => 'شبين الكوم',   'credit_limit' => 200,  'is_active' => true],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(['code' => $c['code']], array_filter([
                'code' => $c['code'],
                'type' => $c['type'],
                'name' => $c['name'],
                'phone' => $c['phone'],
                'email' => $c['email'] ?? null,
                'tax_number' => $c['tax_number'] ?? null,
                'commercial_register' => $c['commercial_register'] ?? null,
                'governate' => $c['governate'],
                'city' => $c['city'],
                'credit_limit' => $c['credit_limit'],
                'balance' => 0,
                'loyalty_points' => 0,
                'is_active' => $c['is_active'],
            ], fn ($v) => $v !== null));
        }

        $this->command->info('✅ Customers seeded (10 records).');
    }
}
