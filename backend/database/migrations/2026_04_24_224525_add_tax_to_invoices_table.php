<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('discount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->decimal('tax_amount', 10, 2)->default(0)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn('tax_amount');
        });
    }
};
