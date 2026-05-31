<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultBranchId = DB::table('branches')->where('is_default', true)->value('id');
        $defaultWarehouseId = DB::table('warehouses')->where('is_default', true)->value('id');

        // users → branch
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('language')
                    ->constrained('branches')->nullOnDelete();
            }
        });

        // invoices → branch + warehouse
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('customer_id')
                    ->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoices', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                    ->constrained('warehouses')->nullOnDelete();
            }
        });

        // expenses → branch
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('created_by')
                    ->constrained('branches')->nullOnDelete();
            }
        });

        // cash_register_sessions → branch
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_register_sessions', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('cashier_id')
                    ->constrained('branches')->nullOnDelete();
            }
        });

        // purchase_orders → branch + warehouse
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('id')
                    ->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_orders', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('branch_id')
                    ->constrained('warehouses')->nullOnDelete();
            }
        });

        // stock_movements → warehouse + batch
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('reference_id')
                    ->constrained('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_movements', 'batch_id')) {
                $table->unsignedBigInteger('batch_id')->nullable()->after('warehouse_id');
            }
        });

        // invoice_items → warehouse + batch
        Schema::table('invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->foreignId('warehouse_id')->nullable()->after('subtotal')
                    ->constrained('warehouses')->nullOnDelete();
            }
            if (! Schema::hasColumn('invoice_items', 'batch_id')) {
                $table->unsignedBigInteger('batch_id')->nullable()->after('warehouse_id');
            }
        });

        // Back-fill branch_id + warehouse_id to existing records
        if ($defaultBranchId) {
            DB::table('users')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('invoices')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('expenses')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('cash_register_sessions')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
            DB::table('purchase_orders')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        }
        if ($defaultWarehouseId) {
            DB::table('invoices')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
            DB::table('purchase_orders')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
            DB::table('stock_movements')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
            if (Schema::hasColumn('invoice_items', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            if (Schema::hasColumn('stock_movements', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
            if (Schema::hasColumn('stock_movements', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
            if (Schema::hasColumn('purchase_orders', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('cash_register_sessions', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'warehouse_id')) {
                $table->dropForeign(['warehouse_id']);
                $table->dropColumn('warehouse_id');
            }
            if (Schema::hasColumn('invoices', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
        });
    }
};
