<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('address')->nullable();
            $table->string('keeper_name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the default warehouse tied to the default branch
        $branchId = DB::table('branches')->where('is_default', true)->value('id');
        DB::table('warehouses')->insert([
            'branch_id' => $branchId,
            'name' => 'المخزن الرئيسي',
            'code' => 'WH-MAIN',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Warehouse stock: per-product quantities per warehouse
        Schema::create('warehouse_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('min_stock')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['warehouse_id', 'product_id']);
        });

        // Back-fill existing product quantities into default warehouse
        $warehouseId = DB::table('warehouses')->where('is_default', true)->value('id');
        $products = DB::table('products')->whereNull('deleted_at')->get(['id', 'quantity', 'min_stock']);
        $rows = $products->map(fn ($p) => [
            'warehouse_id' => $warehouseId,
            'product_id' => $p->id,
            'quantity' => $p->quantity,
            'min_stock' => $p->min_stock,
            'updated_at' => now(),
        ])->toArray();
        if (! empty($rows)) {
            DB::table('warehouse_stock')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_stock');
        Schema::dropIfExists('warehouses');
    }
};
