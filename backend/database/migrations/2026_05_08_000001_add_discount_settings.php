<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * FIX-2: إضافة إعداد حد الخصم الأقصى في جدول الإعدادات
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'max_discount_percent',
            'value' => env('MAX_DISCOUNT_PERCENT', '20'),
            'type' => 'number',
            'group' => 'pos',
            'label_ar' => 'الحد الأقصى للخصم (%)',
            'label_en' => 'Maximum Discount (%)',
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'max_discount_percent')->delete();
    }
};
