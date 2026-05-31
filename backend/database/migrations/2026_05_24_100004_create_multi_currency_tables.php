<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 — Multi-Currency Support
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();           // ISO 4217: USD, EUR, SAR …
            $table->string('name');
            $table->string('symbol', 10);
            $table->decimal('exchange_rate', 18, 8)->default(1.0);  // vs base currency
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('rate_updated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('currency_rate_history', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 3);
            $table->decimal('rate', 18, 8);
            $table->string('source')->default('manual');   // manual, fixer.io, openexchangerates
            $table->timestamp('recorded_at');
            $table->index(['currency_code', 'recorded_at']);
        });

        // Add currency columns to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('currency_code', 3)->default('EGP')->after('total');
            $table->decimal('exchange_rate', 18, 8)->default(1.0)->after('currency_code');
            $table->decimal('total_base_currency', 14, 2)->nullable()->after('exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate', 'total_base_currency']);
        });
        Schema::dropIfExists('currency_rate_history');
        Schema::dropIfExists('currencies');
    }
};
