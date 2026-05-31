<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // reorder_point: quantity at which a purchase suggestion is triggered
            // distinct from min_stock (which just flags the item as "low")
            $table->unsignedInteger('reorder_point')->default(0)->after('min_stock');
            $table->unsignedInteger('reorder_qty')->default(0)->after('reorder_point');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reorder_point', 'reorder_qty']);
        });
    }
};
