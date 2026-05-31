<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Unique client-generated UUID for offline-created invoices.
            // Allows the sync endpoint to be idempotent: if the same UUID arrives twice
            // (e.g. a retry after a network timeout) only the first call creates a row.
            $table->string('offline_uuid', 36)->nullable()->unique()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('offline_uuid');
        });
    }
};
