<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('id', 50)->primary();        // 'basic', 'pro', 'enterprise'
            $table->string('name', 100);
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('annual_price', 10, 2)->nullable();  // yearly total (discounted)
            $table->unsignedInteger('trial_days')->default(14);
            $table->unsignedInteger('max_users')->nullable();     // null = unlimited
            $table->unsignedInteger('max_products')->nullable();  // null = unlimited
            $table->json('features')->nullable();                 // array of feature strings
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
