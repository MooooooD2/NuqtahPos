<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_active');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete()->after('is_locked');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropForeign(['locked_by']);
            $table->dropColumn(['is_locked', 'locked_by', 'locked_at']);
        });
    }
};
