<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->decimal('total', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('final_total', 10, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'wallet']);
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('cashier_name');
            $table->timestamp('date');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
