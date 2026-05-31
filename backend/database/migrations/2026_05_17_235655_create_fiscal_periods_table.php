<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed'])->default('open');

            // Not a FK — avoids circular dependency with journal_entries.closing_entry_id.
            // Application code maintains integrity.
            $table->unsignedBigInteger('closing_entry_id')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date'], 'idx_fp_status_dates');
        });

        // Link journal entries to their fiscal period
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('fiscal_period_id')->nullable()
                ->constrained('fiscal_periods')->nullOnDelete()
                ->after('reversal_of');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['fiscal_period_id']);
            $table->dropColumn('fiscal_period_id');
        });

        Schema::dropIfExists('fiscal_periods');
    }
};
