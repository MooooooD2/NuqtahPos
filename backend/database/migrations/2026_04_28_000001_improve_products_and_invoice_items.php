<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add soft deletes to products
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Store cost_price on invoice_items for accurate profit reporting
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'cost_price')) {
                $table->decimal('cost_price', 12, 2)->default(0)->after('price');
            }
        });

        // Store notes on invoices
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'notes')) {
                $table->text('notes')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
