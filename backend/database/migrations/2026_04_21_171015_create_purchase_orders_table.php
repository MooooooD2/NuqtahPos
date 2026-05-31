<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_name');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);
            $table->enum('status', ['pending', 'received', 'cancelled'])->default('pending');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->string('created_by_name');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_orders');
    }
};
