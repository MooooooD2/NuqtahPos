<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            // Once posted, the entry is locked and cannot be edited or deleted
            $table->boolean('is_posted')->default(false)->after('created_by');
            $table->timestamp('posted_at')->nullable()->after('is_posted');
            $table->foreignId('posted_by')->nullable()->after('posted_at')
                ->constrained('users')->nullOnDelete();

            // Reversal linkage: a reversal entry records which entry it negates
            $table->foreignId('reversal_of')->nullable()->after('posted_by')
                ->constrained('journal_entries')->nullOnDelete();

            $table->index('is_posted');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['posted_by']);
            $table->dropForeign(['reversal_of']);
            $table->dropColumn(['is_posted', 'posted_at', 'posted_by', 'reversal_of']);
        });
    }
};
