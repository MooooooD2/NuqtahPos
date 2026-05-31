<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Check if a named index exists on a table (MySQL only; always false on other drivers). */
    private function hasIndex(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return ! empty(DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$index]
        ));
    }

    public function up(): void
    {
        // Only add indexes on non-FK columns or true composite indexes.
        // Single-column indexes on FK columns are redundant (FK auto-creates one)
        // and cause MySQL error 1553 on rollback.

        Schema::table('invoices', function (Blueprint $table) {
            if (! $this->hasIndex('invoices', 'idx_invoices_status_created')) {
                $table->index(['status', 'created_at'], 'idx_invoices_status_created');
            }
            if (! $this->hasIndex('invoices', 'idx_invoices_payment')) {
                $table->index('payment_method', 'idx_invoices_payment');
            }
            // cashier_id, customer_id etc. skipped — FK indexes already cover them.
        });

        // invoice_items.product_id skipped — FK index already covers it.

        Schema::table('supplier_accounts', function (Blueprint $table) {
            // Composite index — different from the single-column FK index on supplier_id.
            if (! $this->hasIndex('supplier_accounts', 'idx_supplier_accounts_supplier')) {
                $table->index(['supplier_id', 'created_at'], 'idx_supplier_accounts_supplier');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            // Composite index — different from the single-column FK index on product_id.
            if (! $this->hasIndex('stock_movements', 'idx_stock_movements_product')) {
                $table->index(['product_id', 'created_at'], 'idx_stock_movements_product');
            }
            if (! $this->hasIndex('stock_movements', 'idx_stock_movements_type')) {
                $table->index('movement_type', 'idx_stock_movements_type');
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Composite index — different from the single-column FK index on supplier_id.
            if (! $this->hasIndex('purchase_orders', 'idx_purchase_orders_supplier_status')) {
                $table->index(['supplier_id', 'status'], 'idx_purchase_orders_supplier_status');
            }
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            if (! $this->hasIndex('sales_returns', 'idx_sales_returns_status_date')) {
                $table->index(['status', 'return_date'], 'idx_sales_returns_status_date');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty.
        // MySQL error 1553 prevents dropping composite indexes that it has chosen
        // as FK backing indexes. Performance indexes are non-destructive — leaving
        // them in place on rollback is safe and avoids breaking migrations.
    }
};
