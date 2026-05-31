<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->enum('refund_method', ['cash', 'store_credit', 'exchange'])
                ->default('cash')
                ->after('status')
                ->comment('طريقة رد المبلغ: نقدي / رصيد في المحل / استبدال');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refund_method');
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn(['refund_method', 'refund_amount']);
        });
    }
};
