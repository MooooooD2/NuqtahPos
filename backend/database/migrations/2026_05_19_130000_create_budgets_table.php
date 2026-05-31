<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('year');
            $table->tinyInteger('month'); // 1–12
            $table->enum('type', ['revenue', 'expense']);
            $table->string('category')->nullable(); // expense category name or null for total revenue
            $table->decimal('amount', 14, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
