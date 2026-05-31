<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('batch_number');
            $table->string('lot_number')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('original_qty');
            $table->integer('remaining_qty');
            $table->decimal('cost_price', 12, 4)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'exhausted', 'expired'])->default('active');
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id', 'status', 'expiry_date'], 'idx_batch_fefo');
            $table->unique(['product_id', 'warehouse_id', 'batch_number']);
        });

        // Add track_batches flag to products
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('track_batches')->default(false)->after('min_stock');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('track_batches');
        });
        Schema::dropIfExists('product_batches');
    }
};
