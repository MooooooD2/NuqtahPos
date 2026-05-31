<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes identified in the security / performance audit.
 *
 * Each index is guarded with Schema::hasIndex() so the migration is
 * idempotent and can be re-run safely on existing databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        // stock_movements.created_at — used in cashFlowReport, stockValuation, etc.
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! $this->hasIndex('stock_movements', 'stock_movements_created_at_index')) {
                $table->index('created_at', 'stock_movements_created_at_index');
            }
            if (! $this->hasIndex('stock_movements', 'stock_movements_product_id_type_index')) {
                $table->index(['product_id', 'movement_type'], 'stock_movements_product_id_type_index');
            }
        });

        // invoice_items.product_id — join target for COGS and sales reports
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! $this->hasIndex('invoice_items', 'invoice_items_product_id_index')) {
                $table->index('product_id', 'invoice_items_product_id_index');
            }
        });

        // journal_entry_lines.account_id — used in balance recalculation and income statement
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (! $this->hasIndex('journal_entry_lines', 'jel_account_id_index')) {
                $table->index('account_id', 'jel_account_id_index');
            }
        });

        // journal_entries.entry_date — range queries in income statement, balance sheet
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! $this->hasIndex('journal_entries', 'journal_entries_entry_date_index')) {
                $table->index('entry_date', 'journal_entries_entry_date_index');
            }
            if (! $this->hasIndex('journal_entries', 'journal_entries_reference_index')) {
                $table->index(['reference_type', 'reference_id'], 'journal_entries_reference_index');
            }
        });

        // purchase_return_items — used in getReturnableQuantities()
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (! $this->hasIndex('purchase_return_items', 'pri_product_id_index')) {
                $table->index('product_id', 'pri_product_id_index');
            }
        });

        // invoices — cashier + status + created_at used in session stats
        Schema::table('invoices', function (Blueprint $table) {
            if (! $this->hasIndex('invoices', 'invoices_cashier_status_created_index')) {
                $table->index(['cashier_id', 'status', 'created_at'], 'invoices_cashier_status_created_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndexIfExists('invoices_cashier_status_created_index');
        });
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropIndexIfExists('pri_product_id_index');
        });
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropIndexIfExists('journal_entries_reference_index');
            $table->dropIndexIfExists('journal_entries_entry_date_index');
        });
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropIndexIfExists('jel_account_id_index');
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropIndexIfExists('invoice_items_product_id_index');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndexIfExists('stock_movements_product_id_type_index');
            $table->dropIndexIfExists('stock_movements_created_at_index');
        });
    }

    /** Check whether a named index already exists on the given table. */
    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($i) => $i['name'] === $index);
    }
};
