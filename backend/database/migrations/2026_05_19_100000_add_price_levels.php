<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-product wholesale and VIP prices (null = use retail price)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_price', 10, 4)->nullable()->after('price');
            $table->decimal('vip_price', 10, 4)->nullable()->after('wholesale_price');
        });

        // Customer pricing tier — drives which product price column is used at POS
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('price_level', ['retail', 'wholesale', 'vip'])->default('retail')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'vip_price']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('price_level');
        });
    }
};
