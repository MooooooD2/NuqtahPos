<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // #16 #18 #19 تعزيز جدول حركات المخزون
            if (! Schema::hasColumn('stock_movements', 'balance_after')) {
                $table->integer('balance_after')->default(0)->after('quantity');
            }
            if (! Schema::hasColumn('stock_movements', 'reference_type')) {
                $table->string('reference_type', 50)->nullable()->after('reference_id');
            }
            if (! Schema::hasColumn('stock_movements', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('employee_name');
            }

            $table->index(['product_id', 'created_at'], 'idx_sm_product_date');
            $table->index('movement_type', 'idx_sm_type');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['balance_after', 'reference_type', 'ip_address']);
        });
    }
};
