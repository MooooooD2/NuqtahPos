<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        DB::connection('mysql')->table('payment_accounts')->insertOrIgnore([
            [
                'method'         => 'whatsapp',
                'label_ar'       => 'واتساب',
                'label_en'       => 'WhatsApp',
                'icon'           => 'fab fa-whatsapp',
                'color'          => '#25d366',
                'sort_order'     => 0,
                'is_active'      => 1,
                'account_number' => null,
                'account_name'   => null,
                'notes'          => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::connection('mysql')->table('payment_accounts')->where('method', 'whatsapp')->delete();
    }
};
