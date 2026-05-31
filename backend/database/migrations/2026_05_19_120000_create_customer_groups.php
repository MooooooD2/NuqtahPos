<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->enum('price_level', ['retail', 'wholesale', 'vip'])->default('retail');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('customer_group_id')
                ->nullable()
                ->after('code')
                ->constrained('customer_groups')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn('customer_group_id');
        });

        Schema::dropIfExists('customer_groups');
    }
};
