<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track returned quantity per invoice line to avoid re-querying ReturnItems on every return
        if (! Schema::hasColumn('invoice_items', 'returned_qty')) {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->unsignedInteger('returned_qty')->default(0)->after('quantity');
                $table->decimal('returned_tax', 10, 2)->default(0)->after('tax_amount');
            });
        }

        // Partial-receive quality tracking on purchase order items
        if (! Schema::hasColumn('purchase_order_items', 'rejected_qty')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->unsignedInteger('rejected_qty')->default(0)->after('received_quantity');
                $table->enum('quality_status', ['pending', 'passed', 'rejected'])->default('pending')->after('rejected_qty');
            });
        }

        // Cash drawer movements (deposits / withdrawals / adjustments during a session)
        // FK must reference cash_register_sessions (not the default cash_sessions)
        if (! Schema::hasTable('cash_session_movements')) {
            Schema::create('cash_session_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cash_session_id');
                $table->foreign('cash_session_id')
                    ->references('id')
                    ->on('cash_register_sessions')
                    ->cascadeOnDelete();
                $table->enum('type', ['deposit', 'withdrawal', 'adjustment']);
                $table->decimal('amount', 15, 2);
                $table->string('reason')->nullable();
                $table->foreignId('user_id')->constrained();
                $table->timestamps();

                $table->index(['cash_session_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session_movements');

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['rejected_qty', 'quality_status']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['returned_qty', 'returned_tax']);
        });
    }
};
