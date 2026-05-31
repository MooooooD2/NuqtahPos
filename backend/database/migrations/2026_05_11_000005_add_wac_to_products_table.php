<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('avg_cost', 12, 2)->default(0)->after('cost_price');
            $table->decimal('last_cost', 12, 2)->default(0)->after('avg_cost');
        });

        // Seed avg_cost and last_cost from existing cost_price
        DB::table('products')->update([
            'avg_cost' => DB::raw('cost_price'),
            'last_cost' => DB::raw('cost_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['avg_cost', 'last_cost']);
        });
    }
};
