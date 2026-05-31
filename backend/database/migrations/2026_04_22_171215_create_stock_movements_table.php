<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->integer('quantity');
            $table->enum('movement_type', ['add', 'remove', 'sale', 'return', 'purchase', 'adjustment', 'adjustment_add', 'adjustment_remove']);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
