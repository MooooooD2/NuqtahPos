<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Extend status to include draft and approved
            $table->string('status', 20)->default('draft')->change();

            if (! Schema::hasColumn('purchase_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by_name')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('purchase_orders', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at', 'rejection_reason']);
            $table->enum('status', ['pending', 'received', 'cancelled'])->default('pending')->change();
        });
    }
};
