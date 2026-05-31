<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tax ID for suppliers — required for input-VAT reconciliation
        // (customers already have tax_number from earlier migration)
        if (! Schema::hasColumn('suppliers', 'tax_number')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('tax_number', 20)->nullable()->after('phone');
            });
        }

        // Company tax registration number seeded as a setting
        $settingSeeds = [
            [
                'key' => 'company_tax_number',
                'value' => '',
                'group' => 'tax',
                'label_ar' => 'الرقم الضريبي للشركة',
                'label_en' => 'Company Tax Registration Number',
                'type' => 'string',
            ],
            [
                'key' => 'ip_whitelist',
                'value' => '',
                'group' => 'security',
                'label_ar' => 'قائمة IPs المسموح بها (مفصولة بفاصلة، فارغة = بلا قيود)',
                'label_en' => 'Allowed IP Whitelist (comma-separated, empty = no restriction)',
                'type' => 'string',
            ],
        ];

        foreach ($settingSeeds as $seed) {
            if (! DB::table('settings')->where('key', $seed['key'])->exists()) {
                DB::table('settings')->insert(array_merge($seed, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });

        DB::table('settings')->where('key', 'company_tax_number')->delete();
    }
};
