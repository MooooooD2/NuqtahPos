<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MODIFY COLUMN is MySQL/MariaDB only — SQLite (used in tests) handles ENUMs as TEXT,
        // so we skip the statement when running on SQLite.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement(
                "ALTER TABLE invoices MODIFY COLUMN payment_method
                 ENUM('cash','card','transfer','wallet','credit') NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            // Remove rows using 'credit' before reverting the enum, otherwise MySQL rejects it.
            DB::statement("UPDATE invoices SET payment_method = 'cash' WHERE payment_method = 'credit'");

            DB::statement(
                "ALTER TABLE invoices MODIFY COLUMN payment_method
                 ENUM('cash','card','transfer','wallet') NOT NULL"
            );
        }
    }
};
