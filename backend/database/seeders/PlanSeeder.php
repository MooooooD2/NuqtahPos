<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'id' => 'basic',
                'name' => 'Basic',
                'monthly_price' => 49,
                'annual_price' => 490,
                'trial_days' => 14,
                'max_users' => 3,
                'max_products' => 500,
                'sort_order' => 1,
                'features' => [
                    ['ar' => 'نقطة البيع (POS) السريعة',            'en' => 'Fast Point of Sale (POS)'],
                    ['ar' => 'إدارة المخزون الأساسية',               'en' => 'Basic inventory management'],
                    ['ar' => 'تقارير المبيعات الأساسية',              'en' => 'Basic sales reports'],
                    ['ar' => 'حتى 3 مستخدمين',                       'en' => 'Up to 3 users'],
                    ['ar' => 'حتى 500 منتج',                          'en' => 'Up to 500 products'],
                    ['ar' => 'دعم عبر البريد الإلكتروني',             'en' => 'Email support'],
                ],
            ],
            [
                'id' => 'pro',
                'name' => 'Pro',
                'monthly_price' => 99,
                'annual_price' => 990,
                'trial_days' => 14,
                'max_users' => 10,
                'max_products' => null,
                'sort_order' => 2,
                'features' => [
                    ['ar' => 'كل مميزات Basic',                       'en' => 'Everything in Basic'],
                    ['ar' => 'منتجات غير محدودة',                     'en' => 'Unlimited products'],
                    ['ar' => 'تقارير وتحليلات متقدمة',                'en' => 'Advanced reports & analytics'],
                    ['ar' => 'وحدة المحاسبة المتكاملة',               'en' => 'Integrated accounting module'],
                    ['ar' => 'أوامر الشراء وإدارة الموردين',          'en' => 'Purchase orders & supplier management'],
                    ['ar' => 'حتى 10 مستخدمين',                       'en' => 'Up to 10 users'],
                    ['ar' => 'تكامل واتساب',                           'en' => 'WhatsApp integration'],
                    ['ar' => 'دعم ذو أولوية',                          'en' => 'Priority support'],
                ],
            ],
            [
                'id' => 'enterprise',
                'name' => 'Enterprise',
                'monthly_price' => 199,
                'annual_price' => 1990,
                'trial_days' => 30,
                'max_users' => null,
                'max_products' => null,
                'sort_order' => 3,
                'features' => [
                    ['ar' => 'كل مميزات Pro',                          'en' => 'Everything in Pro'],
                    ['ar' => 'مستخدمون غير محدودون',                  'en' => 'Unlimited users'],
                    ['ar' => 'فروع ومستودعات متعددة',                 'en' => 'Multi-branch & warehouses'],
                    ['ar' => 'مجموعات عملاء وعروض ترويجية',           'en' => 'Customer groups & promotions'],
                    ['ar' => 'تقارير الميزانية مقابل الفعلي',         'en' => 'Budget vs Actual reports'],
                    ['ar' => 'تقارير مالية احترافية',                 'en' => 'Professional financial reports'],
                    ['ar' => 'وصول كامل لـ API',                      'en' => 'Full API access'],
                    ['ar' => 'مدير حساب مخصص',                        'en' => 'Dedicated account manager'],
                ],
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(['id' => $data['id']], $data);
        }
    }
}
