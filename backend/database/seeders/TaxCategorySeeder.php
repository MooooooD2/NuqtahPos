<?php

namespace Database\Seeders;

use App\Models\TaxCategory;
use Illuminate\Database\Seeder;

class TaxCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'STANDARD', 'name_ar' => 'الضريبة القياسية', 'name_en' => 'Standard Rate',  'rate' => 14.00, 'is_default' => true,  'is_active' => true],
            ['code' => 'ZERO',     'name_ar' => 'صفري المعدل',       'name_en' => 'Zero Rate',       'rate' => 0.00,  'is_default' => false, 'is_active' => true],
            ['code' => 'EXEMPT',   'name_ar' => 'معفي من الضريبة',   'name_en' => 'Tax Exempt',      'rate' => 0.00,  'is_default' => false, 'is_active' => true],
        ];

        foreach ($categories as $cat) {
            TaxCategory::firstOrCreate(['code' => $cat['code']], $cat);
        }
    }
}
