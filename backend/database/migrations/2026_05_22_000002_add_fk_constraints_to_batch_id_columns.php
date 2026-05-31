<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // warehouse_transfer_items.batch_id had no FK — add it with nullOnDelete
        Schema::table('warehouse_transfer_items', function (Blueprint $table) {
            $table->foreign('batch_id')
                ->references('id')
                ->on('product_batches')
                ->nullOnDelete();
        });

        // stock_movements.batch_id had no FK — add it with nullOnDelete
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('batch_id')
                ->references('id')
                ->on('product_batches')
                ->nullOnDelete();
        });

        // invoice_items.batch_id had no FK — add it with nullOnDelete
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('batch_id')
                ->references('id')
                ->on('product_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });

        Schema::table('warehouse_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
        });
    }
};
