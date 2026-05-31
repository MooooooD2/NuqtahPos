<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->enum('reason', ['expired', 'damaged', 'theft', 'other'])->default('other');
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_records');
    }
};
