<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_type', 50)->default('manual');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->integer('original_qty');
            $table->integer('remaining_qty');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->timestamps();

            // Composite index for fast FIFO/LIFO lookups
            $table->index(
                ['product_id', 'warehouse_id', 'remaining_qty', 'created_at'],
                'idx_cost_layers_lookup'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_layers');
    }
};
