<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number');
            $table->string('customer_name')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->date('return_date');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('processed_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
