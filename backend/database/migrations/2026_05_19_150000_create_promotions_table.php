<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'buy_x_get_y'])->default('percentage');
            $table->decimal('value', 10, 2)->default(0);    // discount % or fixed amount
            $table->unsignedInteger('buy_qty')->default(0); // buy X
            $table->unsignedInteger('get_qty')->default(0); // get Y free
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete(); // specific product; null = any
            $table->string('product_category')->nullable(); // filter by product.category string
            $table->decimal('min_order_amount', 14, 2)->default(0); // 0 = no minimum
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
