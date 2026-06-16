<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key'      => 'saas_whatsapp_number',
            'value'    => '',
            'type'     => 'string',
            'group'    => 'general',
            'label_ar' => 'رقم واتساب للتواصل',
            'label_en' => 'WhatsApp Contact Number',
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'saas_whatsapp_number')->delete();
    }
};
