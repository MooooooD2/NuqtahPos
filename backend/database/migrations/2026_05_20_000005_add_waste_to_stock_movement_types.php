<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN movement_type ENUM(
            'add','remove','sale','return','purchase',
            'adjustment','adjustment_add','adjustment_remove',
            'transfer_in','transfer_out','return_to_supplier',
            'waste','recipe_deduction'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN movement_type ENUM(
            'add','remove','sale','return','purchase',
            'adjustment','adjustment_add','adjustment_remove',
            'transfer_in','transfer_out','return_to_supplier'
        ) NOT NULL");
    }
};
