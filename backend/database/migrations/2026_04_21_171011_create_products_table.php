<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->integer('quantity')->default(0);
            $table->integer('min_stock')->default(5);
            $table->string('barcode')->unique()->nullable();
            $table->string('category')->nullable();
            $table->string('supplier')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
