<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General - عام
            ['key' => 'store_name',       'value' => 'نظام نقطة البيع',   'type' => 'string',  'group' => 'general', 'label_ar' => 'اسم المتجر',           'label_en' => 'Store Name'],
            ['key' => 'store_address',    'value' => 'القاهرة، مصر',       'type' => 'string',  'group' => 'general', 'label_ar' => 'عنوان المتجر',         'label_en' => 'Store Address'],
            ['key' => 'store_phone',      'value' => '01000000000',        'type' => 'string',  'group' => 'general', 'label_ar' => 'هاتف المتجر',          'label_en' => 'Store Phone'],
            ['key' => 'store_email',      'value' => '',                   'type' => 'string',  'group' => 'general', 'label_ar' => 'البريد الإلكتروني',    'label_en' => 'Store Email'],
            ['key' => 'currency',         'value' => 'EGP',                'type' => 'string',  'group' => 'general', 'label_ar' => 'العملة',               'label_en' => 'Currency'],
            ['key' => 'currency_symbol',  'value' => 'ج.م',                'type' => 'string',  'group' => 'general', 'label_ar' => 'رمز العملة',           'label_en' => 'Currency Symbol'],
            ['key' => 'default_language', 'value' => 'ar',                 'type' => 'string',  'group' => 'general', 'label_ar' => 'اللغة الافتراضية',     'label_en' => 'Default Language'],

            // Tax - الضريبة
            ['key' => 'tax_enabled',      'value' => '0',                  'type' => 'boolean', 'group' => 'tax',     'label_ar' => 'تفعيل الضريبة',        'label_en' => 'Enable Tax'],
            ['key' => 'tax_rate',         'value' => '14',                 'type' => 'number',  'group' => 'tax',     'label_ar' => 'نسبة الضريبة (%)',     'label_en' => 'Tax Rate (%)'],
            ['key' => 'tax_name_ar',      'value' => 'ضريبة القيمة المضافة', 'type' => 'string', 'group' => 'tax',     'label_ar' => 'اسم الضريبة (عربي)',   'label_en' => 'Tax Name (Arabic)'],
            ['key' => 'tax_name_en',      'value' => 'VAT',                'type' => 'string',  'group' => 'tax',     'label_ar' => 'اسم الضريبة (إنجليزي)', 'label_en' => 'Tax Name (English)'],
            ['key' => 'tax_inclusive',    'value' => '0',                  'type' => 'boolean', 'group' => 'tax',     'label_ar' => 'السعر شامل الضريبة',   'label_en' => 'Price Includes Tax'],
            ['key' => 'tax_number',       'value' => '',                   'type' => 'string',  'group' => 'tax',     'label_ar' => 'الرقم الضريبي',        'label_en' => 'Tax Number'],

            // Invoice - الفاتورة
            ['key' => 'invoice_prefix',   'value' => 'INV',                'type' => 'string',  'group' => 'invoice', 'label_ar' => 'بادئة رقم الفاتورة',  'label_en' => 'Invoice Prefix'],
            ['key' => 'invoice_footer',   'value' => 'شكراً لتعاملكم معنا', 'type' => 'string', 'group' => 'invoice', 'label_ar' => 'تذييل الفاتورة',       'label_en' => 'Invoice Footer'],
            ['key' => 'show_tax_invoice', 'value' => '1',                  'type' => 'boolean', 'group' => 'invoice', 'label_ar' => 'إظهار الضريبة في الفاتورة', 'label_en' => 'Show Tax on Invoice'],
            ['key' => 'auto_print',       'value' => '0',                  'type' => 'boolean', 'group' => 'invoice', 'label_ar' => 'طباعة تلقائية',        'label_en' => 'Auto Print'],

            // POS - نقطة البيع
            ['key' => 'pos_sound',             'value' => '1',    'type' => 'boolean', 'group' => 'pos',     'label_ar' => 'صوت عند المسح',             'label_en' => 'Beep on Scan'],
            ['key' => 'low_stock_alert',        'value' => '1',    'type' => 'boolean', 'group' => 'pos',     'label_ar' => 'تنبيه المخزون المنخفض',     'label_en' => 'Low Stock Alert'],
            ['key' => 'allow_negative_stock',   'value' => '0',    'type' => 'boolean', 'group' => 'pos',     'label_ar' => 'السماح بمخزون سالب',        'label_en' => 'Allow Negative Stock'],
            ['key' => 'default_payment',        'value' => 'cash', 'type' => 'string',  'group' => 'pos',     'label_ar' => 'طريقة الدفع الافتراضية',    'label_en' => 'Default Payment Method'],
            ['key' => 'max_discount_percent',   'value' => '20',   'type' => 'number',  'group' => 'pos',     'label_ar' => 'الحد الأقصى للخصم (%)',     'label_en' => 'Maximum Discount (%)'],

            // Loyalty Points - نقاط الولاء
            ['key' => 'loyalty_enabled',    'value' => '0',   'type' => 'boolean', 'group' => 'loyalty', 'label_ar' => 'تفعيل نقاط الولاء',           'label_en' => 'Enable Loyalty Points'],
            ['key' => 'loyalty_earn_rate',  'value' => '10',  'type' => 'number',  'group' => 'loyalty', 'label_ar' => 'كل كم جنيه = نقطة',           'label_en' => 'Spend per Point (EGP)'],
            ['key' => 'loyalty_redeem_value', 'value' => '0.5', 'type' => 'number',  'group' => 'loyalty', 'label_ar' => 'قيمة النقطة عند الاسترداد',   'label_en' => 'Point Redeem Value (EGP)'],
            ['key' => 'loyalty_min_redeem', 'value' => '100', 'type' => 'number',  'group' => 'loyalty', 'label_ar' => 'الحد الأدنى للنقاط للاسترداد', 'label_en' => 'Minimum Points to Redeem'],

            // POS extended
            ['key' => 'allow_cashier_price_change', 'value' => '0',  'type' => 'boolean', 'group' => 'pos',        'label_ar' => 'السماح للكاشير بتغيير السعر', 'label_en' => 'Allow Cashier Price Change'],

            // Accounting - المحاسبة
            ['key' => 'max_daily_withdrawal',  'value' => '0',    'type' => 'number', 'group' => 'accounting', 'label_ar' => 'الحد الأقصى للسحب اليومي',    'label_en' => 'Max Daily Withdrawal'],
            ['key' => 'min_cash_balance',      'value' => '0',    'type' => 'number', 'group' => 'accounting', 'label_ar' => 'الحد الأدنى لرصيد الخزينة',   'label_en' => 'Min Cash Balance'],
            ['key' => 'cash_account_code',     'value' => '1001', 'type' => 'string', 'group' => 'accounting', 'label_ar' => 'كود حساب الخزينة',             'label_en' => 'Cash Account Code'],
            ['key' => 'revenue_account_code',  'value' => '4001', 'type' => 'string', 'group' => 'accounting', 'label_ar' => 'كود حساب الإيرادات',           'label_en' => 'Revenue Account Code'],
            ['key' => 'profit_margin_target',  'value' => '0',    'type' => 'number', 'group' => 'accounting', 'label_ar' => 'هدف هامش الربح (%)',           'label_en' => 'Profit Margin Target (%)'],

            // Inventory - المخزون
            ['key' => 'inventory_valuation_method', 'value' => 'weighted_average', 'type' => 'string', 'group' => 'inventory', 'label_ar' => 'طريقة تقييم المخزون', 'label_en' => 'Inventory Valuation Method'],

            // Printing - الطباعة الحرارية
            ['key' => 'print_on_sale',          'value' => '0',       'type' => 'boolean', 'group' => 'printing', 'label_ar' => 'طباعة تلقائية عند البيع',            'label_en' => 'Auto Print on Sale'],
            ['key' => 'print_on_return',         'value' => '0',       'type' => 'boolean', 'group' => 'printing', 'label_ar' => 'طباعة تلقائية عند المرتجع',          'label_en' => 'Auto Print on Return'],
            ['key' => 'print_on_shift_close',    'value' => '0',       'type' => 'boolean', 'group' => 'printing', 'label_ar' => 'طباعة تقرير الوردية عند الإغلاق',    'label_en' => 'Print Shift Report on Close'],
            ['key' => 'receipt_template',        'value' => 'default', 'type' => 'string',  'group' => 'printing', 'label_ar' => 'قالب الإيصال',                       'label_en' => 'Receipt Template'],
            ['key' => 'receipt_copies',          'value' => '1',       'type' => 'number',  'group' => 'printing', 'label_ar' => 'عدد نسخ الإيصال',                    'label_en' => 'Receipt Copies'],
            ['key' => 'receipt_show_qr',         'value' => '1',       'type' => 'boolean', 'group' => 'printing', 'label_ar' => 'إظهار QR كود الضريبة',               'label_en' => 'Show ETA QR Code'],
            ['key' => 'receipt_show_barcode',    'value' => '0',       'type' => 'boolean', 'group' => 'printing', 'label_ar' => 'إظهار الباركود على الإيصال',         'label_en' => 'Show Barcode on Receipt'],
            ['key' => 'print_fallback_browser',  'value' => '1',       'type' => 'boolean', 'group' => 'printing', 'label_ar' => 'الطباعة عبر المتصفح عند الفشل',      'label_en' => 'Fallback to Browser Print'],
            ['key' => 'kitchen_printer_id',      'value' => '',        'type' => 'string',  'group' => 'printing', 'label_ar' => 'معرف طابعة المطبخ',                  'label_en' => 'Kitchen Printer ID'],
            ['key' => 'barcode_printer_id',      'value' => '',        'type' => 'string',  'group' => 'printing', 'label_ar' => 'معرف طابعة الباركود',                'label_en' => 'Barcode Printer ID'],
            ['key' => 'tax_registration_number', 'value' => '',        'type' => 'string',  'group' => 'printing', 'label_ar' => 'رقم التسجيل الضريبي على الإيصال',   'label_en' => 'Tax Registration Number'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->updateOrInsert(['key' => $s['key']], $s);
        }
    }
}
