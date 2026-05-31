<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('hold_number')->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('cashier_name');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->json('cart_data');           // full cart snapshot: items[], discount, payment_method, notes
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('notes')->nullable();
            $table->enum('status', ['held', 'resumed', 'discarded', 'expired'])->default('held');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_invoices');
    }
};
