<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kitchen_orders')) {
            Schema::create('kitchen_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('order_number', 20);
                $table->string('table_number', 20)->nullable();
                $table->string('order_type', 20)->default('dine_in');
                $table->enum('status', ['pending', 'preparing', 'ready', 'served', 'cancelled'])->default('pending');
                $table->text('notes')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('served_at')->nullable();
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->timestamps();

                $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            });
        }

        if (! Schema::hasTable('kitchen_order_items')) {
            Schema::create('kitchen_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('kitchen_order_id')->index();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name', 200);
                $table->decimal('quantity', 10, 2);
                $table->string('unit', 50)->nullable();
                $table->text('notes')->nullable();
                $table->enum('status', ['pending', 'preparing', 'done', 'cancelled'])->default('pending');
                $table->timestamps();

                $table->foreign('kitchen_order_id')->references('id')->on('kitchen_orders')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_items');
        Schema::dropIfExists('kitchen_orders');
    }
};
