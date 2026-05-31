<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_number')->unique();
            $table->foreignId('cashier_id')->constrained('users');
            $table->string('cashier_name');
            $table->decimal('opening_amount', 10, 2)->default(0);   // رصيد البداية
            $table->decimal('expected_cash', 10, 2)->default(0);    // المبيعات نقدي المتوقعة
            $table->decimal('actual_cash', 10, 2)->nullable();       // الموجود فعلاً في الدرج
            $table->decimal('difference', 10, 2)->nullable();        // الفرق (عجز/زيادة)
            $table->decimal('total_sales', 10, 2)->default(0);       // إجمالي المبيعات
            $table->decimal('total_returns', 10, 2)->default(0);     // إجمالي المرتجعات
            $table->decimal('total_card', 10, 2)->default(0);
            $table->decimal('total_transfer', 10, 2)->default(0);
            $table->integer('invoices_count')->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};
