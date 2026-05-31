<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('from_warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'in_transit', 'received', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('batch_id')->nullable(); // soft ref to product_batches
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
        Schema::dropIfExists('warehouse_transfers');
    }
};
