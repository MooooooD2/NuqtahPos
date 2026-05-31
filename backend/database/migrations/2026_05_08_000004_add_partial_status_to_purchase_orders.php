<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('pending','partial','received','cancelled') DEFAULT 'pending'");
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('discrepancy')->default(0)->after('received_quantity');
            $table->text('discrepancy_notes')->nullable()->after('discrepancy');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE purchase_orders MODIFY status ENUM('pending','received','cancelled') DEFAULT 'pending'");
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['discrepancy', 'discrepancy_notes']);
        });
    }
};
