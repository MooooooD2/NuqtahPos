<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cashback configuration
        if (! Schema::hasTable('cashback_rules')) {
            Schema::create('cashback_rules', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->decimal('percentage', 5, 2)->default(0);  // % of invoice total
                $table->decimal('min_purchase', 15, 2)->default(0); // minimum purchase to earn
                $table->decimal('max_cashback', 15, 2)->nullable(); // cap per transaction
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Cashback ledger per customer
        if (! Schema::hasTable('cashback_transactions')) {
            Schema::create('cashback_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('invoice_id')->nullable()->index();
                $table->enum('type', ['earned', 'redeemed', 'expired', 'adjusted']);
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->string('description', 255)->nullable();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            });
        }

        // Add cashback balance column to customers
        if (!Schema::hasColumn('customers', 'cashback_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('cashback_balance', 15, 2)->default(0)->after('loyalty_points');
            });
        }

        // Add cashback fields to invoices
        if (!Schema::hasColumn('invoices', 'cashback_earned')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->decimal('cashback_earned', 15, 2)->default(0)->after('loyalty_discount');
                $table->decimal('cashback_redeemed', 15, 2)->default(0)->after('cashback_earned');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cashback_transactions');
        Schema::dropIfExists('cashback_rules');
    }
};
