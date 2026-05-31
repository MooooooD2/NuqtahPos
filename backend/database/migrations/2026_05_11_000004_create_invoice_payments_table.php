<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'card', 'transfer', 'wallet', 'credit', 'voucher']);
            $table->decimal('amount', 12, 2);
            $table->string('reference', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_split_payment')->default(false)->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_split_payment');
        });

        Schema::dropIfExists('invoice_payments');
    }
};
