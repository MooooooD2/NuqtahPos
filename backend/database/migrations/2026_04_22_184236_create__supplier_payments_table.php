<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_name');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['cash', 'card', 'transfer', 'check'])->default('cash');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
