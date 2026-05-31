<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql'; // central DB only

    public function up(): void
    {
        if (! Schema::connection('mysql')->hasTable('payment_accounts')) {
            Schema::connection('mysql')->create('payment_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('method')->unique();
                $table->string('account_number')->nullable();
                $table->string('account_name')->nullable();
                $table->string('notes')->nullable();
                $table->string('icon')->default('fas fa-wallet');
                $table->string('color')->default('#374151');
                $table->string('label_ar');
                $table->string('label_en');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        DB::connection('mysql')->table('payment_accounts')->insertOrIgnore([
            ['method' => 'instapay', 'label_ar' => 'إنستاباي',   'label_en' => 'InstaPay',      'icon' => 'fas fa-bolt',       'color' => '#7c3aed', 'sort_order' => 1, 'is_active' => 1, 'account_number' => null, 'account_name' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'vodafone', 'label_ar' => 'فودافون كاش', 'label_en' => 'Vodafone Cash', 'icon' => 'fas fa-mobile-alt', 'color' => '#dc2626', 'sort_order' => 2, 'is_active' => 1, 'account_number' => null, 'account_name' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'etisalat', 'label_ar' => 'اتصالات كاش', 'label_en' => 'Etisalat Cash', 'icon' => 'fas fa-mobile-alt', 'color' => '#059669', 'sort_order' => 3, 'is_active' => 1, 'account_number' => null, 'account_name' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'orange',   'label_ar' => 'أورنج موني',  'label_en' => 'Orange Money',  'icon' => 'fas fa-mobile-alt', 'color' => '#ea580c', 'sort_order' => 4, 'is_active' => 1, 'account_number' => null, 'account_name' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'fawry',    'label_ar' => 'فوري',         'label_en' => 'Fawry',         'icon' => 'fas fa-store-alt',  'color' => '#d97706', 'sort_order' => 5, 'is_active' => 1, 'account_number' => null, 'account_name' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
            ['method' => 'bank',     'label_ar' => 'تحويل بنكي',  'label_en' => 'Bank Transfer', 'icon' => 'fas fa-university', 'color' => '#1d4ed8', 'sort_order' => 6, 'is_active' => 1, 'account_number' => null, 'account_name' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('payment_accounts');
    }
};
