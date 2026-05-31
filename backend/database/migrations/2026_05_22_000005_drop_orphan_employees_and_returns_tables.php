<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Superseded by users table + Spatie roles (see 2026_04_21_171010)
        Schema::dropIfExists('employees');

        // Superseded by sales_returns + return_items tables (see 2026_04_21_171017)
        Schema::dropIfExists('returns');
    }

    public function down(): void
    {
        // These tables are intentionally orphaned; no recreation needed.
    }
};
