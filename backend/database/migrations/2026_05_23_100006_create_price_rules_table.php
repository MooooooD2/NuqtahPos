<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_rules')) {
            Schema::create('price_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->text('description')->nullable();

                // Rule type
                $table->enum('rule_type', [
                    'happy_hour',     // time-based discount
                    'bulk_discount',  // quantity-based
                    'day_of_week',    // specific days
                    'loyalty_tier',   // based on customer tier
                    'category',       // product category discount
                    'flat_price',     // override price directly
                ])->default('happy_hour');

                // Discount
                $table->enum('discount_type', ['percentage', 'fixed_amount', 'new_price'])->default('percentage');
                $table->decimal('discount_value', 10, 2)->default(0);

                // Scope
                $table->json('product_ids')->nullable();    // null = all products
                $table->json('category_ids')->nullable();
                $table->unsignedBigInteger('customer_group_id')->nullable();

                // Time constraints (happy hour)
                $table->time('time_start')->nullable();     // e.g. 15:00
                $table->time('time_end')->nullable();       // e.g. 17:00
                $table->json('days_of_week')->nullable();   // [1,2,3,4,5] = Mon-Fri

                // Date range
                $table->date('valid_from')->nullable();
                $table->date('valid_until')->nullable();

                // Quantity constraints (bulk)
                $table->decimal('min_quantity', 10, 2)->nullable();
                $table->integer('priority')->default(10);   // higher = applied first
                $table->boolean('is_active')->default(true);
                $table->boolean('stackable')->default(false); // combine with other rules?

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
    }
};
