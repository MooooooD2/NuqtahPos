<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Guard: MySQL only — always returns false on SQLite so tests are unaffected. */
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
        // ── journal_entries ──────────────────────────────────────────────────
        // Every accounting report (income statement, balance sheet, account
        // statement) filters or sorts on entry_date. No index existed before.
        Schema::table('journal_entries', function (Blueprint $table) {
            if (! $this->hasIndex('journal_entries', 'idx_je_entry_date')) {
                $table->index('entry_date', 'idx_je_entry_date');
            }
            // Composite for postEntry / reversal queries: posted=true within a date range.
            if (Schema::hasColumn('journal_entries', 'is_posted')
                && ! $this->hasIndex('journal_entries', 'idx_je_posted_date')) {
                $table->index(['is_posted', 'entry_date'], 'idx_je_posted_date');
            }
        });

        // ── journal_entry_lines ──────────────────────────────────────────────
        // Account statement joins lines → entry filtered by account.
        // Composite (account_id, entry_id) resolves the account filter and walks
        // rows in entry order without a separate filesort.
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            if (! $this->hasIndex('journal_entry_lines', 'idx_jel_account_entry')) {
                $table->index(['account_id', 'entry_id'], 'idx_jel_account_entry');
            }
        });

        // ── expenses ─────────────────────────────────────────────────────────
        // Cash-flow report: WHERE expense_date BETWEEN ? AND ?  (no index existed)
        // Profit report: GROUP BY category_id WHERE expense_date in range
        Schema::table('expenses', function (Blueprint $table) {
            if (! $this->hasIndex('expenses', 'idx_expenses_date')) {
                $table->index('expense_date', 'idx_expenses_date');
            }
            if (! $this->hasIndex('expenses', 'idx_expenses_date_category')) {
                $table->index(['expense_date', 'category_id'], 'idx_expenses_date_category');
            }
            if (! $this->hasIndex('expenses', 'idx_expenses_payment_method')) {
                $table->index('payment_method', 'idx_expenses_payment_method');
            }
        });

        // ── invoice_payments ─────────────────────────────────────────────────
        // Cash-flow: GROUP BY method — the FK index on invoice_id does not help
        // the aggregation. A standalone method index improves groupBy performance.
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (! $this->hasIndex('invoice_payments', 'idx_invoice_payments_method')) {
                $table->index('method', 'idx_invoice_payments_method');
            }
        });

        // ── supplier_payments ────────────────────────────────────────────────
        // Cash-flow outflows: WHERE payment_date BETWEEN ? AND ?
        Schema::table('supplier_payments', function (Blueprint $table) {
            if (! $this->hasIndex('supplier_payments', 'idx_supplier_payments_date')) {
                $table->index('payment_date', 'idx_supplier_payments_date');
            }
            if (! $this->hasIndex('supplier_payments', 'idx_supplier_payments_method')) {
                $table->index('payment_method', 'idx_supplier_payments_method');
            }
        });

        // ── purchase_returns ─────────────────────────────────────────────────
        // Cash-flow: WHERE refund_method='cash' AND status='completed' AND return_date BETWEEN
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (! $this->hasIndex('purchase_returns', 'idx_pr_status_date')) {
                $table->index(['status', 'return_date'], 'idx_pr_status_date');
            }
            if (! $this->hasIndex('purchase_returns', 'idx_pr_refund_method')) {
                $table->index('refund_method', 'idx_pr_refund_method');
            }
        });

        // ── held_invoices ────────────────────────────────────────────────────
        // Active holds: WHERE expires_at IS NULL OR expires_at > now()
        // Expiry cleanup: WHERE expires_at < now()
        Schema::table('held_invoices', function (Blueprint $table) {
            if (! $this->hasIndex('held_invoices', 'idx_held_invoices_expires_at')) {
                $table->index('expires_at', 'idx_held_invoices_expires_at');
            }
        });

        // ── cash_register_sessions ───────────────────────────────────────────
        // Open session lookup: WHERE status = 'open'
        // Session history: WHERE opened_at BETWEEN ? AND ?
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            if (! $this->hasIndex('cash_register_sessions', 'idx_crs_status')) {
                $table->index('status', 'idx_crs_status');
            }
            if (! $this->hasIndex('cash_register_sessions', 'idx_crs_opened_at')) {
                $table->index('opened_at', 'idx_crs_opened_at');
            }
        });

        // ── products ─────────────────────────────────────────────────────────
        // Product listing: WHERE category = ?  (no index existed)
        Schema::table('products', function (Blueprint $table) {
            if (! $this->hasIndex('products', 'idx_products_category')) {
                $table->index('category', 'idx_products_category');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty — same rationale as add_performance_indexes:
        // MySQL error 1553 prevents dropping composite indexes chosen as FK backing
        // indexes. Performance indexes are non-destructive to leave in place.
    }
};
