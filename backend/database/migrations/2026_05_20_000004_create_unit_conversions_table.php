<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_unit_id')->constrained('units');
            $table->foreignId('sale_unit_id')->constrained('units');
            $table->decimal('conversion_factor', 14, 6)->comment('1 وحدة شراء = كذا وحدة بيع');
            $table->timestamps();

            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
    }
};
