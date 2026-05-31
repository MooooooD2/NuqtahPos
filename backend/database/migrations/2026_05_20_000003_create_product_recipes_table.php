<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->comment('كمية المكوّن لكل وحدة من المنتج النهائي');
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recipes');
    }
};
