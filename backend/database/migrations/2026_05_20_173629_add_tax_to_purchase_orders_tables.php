<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Track total input tax (VAT on purchases) on the PO header
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('tax_amount', 12, 2)->default(0)->after('discount');
        });

        // Track per-item tax rate and computed tax for Net Tax Payable report
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }
};
