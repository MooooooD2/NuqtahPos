<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * إضافة عمودين لتتبع المبلغ المدفوع فعلياً والباقي للزبون
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // المبلغ اللي دفعه الزبون فعلاً (للكاش فقط)
            $table->decimal('cash_received', 10, 2)->nullable()->after('final_total');
            // الباقي = cash_received - final_total
            $table->decimal('change_amount', 10, 2)->nullable()->after('cash_received');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['cash_received', 'change_amount']);
        });
    }
};
