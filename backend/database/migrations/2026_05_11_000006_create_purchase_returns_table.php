<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_name');
            $table->decimal('total_amount', 12, 2);
            $table->text('reason')->nullable();
            $table->enum('refund_method', ['cash', 'credit_note'])->default('credit_note');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->foreignId('processed_by')->constrained('users');
            $table->string('processed_by_name');
            $table->date('return_date');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 4);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
