<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qr_tables')) {
            Schema::create('qr_tables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('table_name', 50);
                $table->string('token', 64)->unique();
                $table->string('qr_code_path')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('capacity')->default(4);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('qr_orders')) {
            Schema::create('qr_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('qr_table_id')->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->unsignedBigInteger('kitchen_order_id')->nullable();
                $table->string('customer_name', 100)->nullable();
                $table->string('customer_phone', 30)->nullable();
                $table->enum('status', ['pending', 'accepted', 'preparing', 'ready', 'completed', 'cancelled'])->default('pending');
                $table->text('notes')->nullable();
                $table->decimal('total', 15, 2)->default(0);
                $table->timestamps();

                $table->foreign('qr_table_id')->references('id')->on('qr_tables')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('qr_order_items')) {
            Schema::create('qr_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('qr_order_id')->index();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('product_name', 200);
                $table->decimal('price', 15, 2);
                $table->decimal('quantity', 10, 2);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('qr_order_id')->references('id')->on('qr_orders')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_order_items');
        Schema::dropIfExists('qr_orders');
        Schema::dropIfExists('qr_tables');
    }
};
