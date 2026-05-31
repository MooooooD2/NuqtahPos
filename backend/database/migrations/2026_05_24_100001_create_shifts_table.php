<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Shift & Employee Management
 * Tracks work shifts, clock-in/out, and shift summaries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Shift definitions (templates)
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // e.g. "Morning", "Evening"
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(30);
            $table->boolean('is_overnight')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Actual shift instances assigned to employees
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('shift_date');
            $table->timestamp('clock_in_at')->nullable();
            $table->timestamp('clock_out_at')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->enum('status', ['scheduled', 'active', 'completed', 'missed', 'excused'])
                  ->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();              // GPS coords, device info
            $table->timestamps();

            $table->index(['user_id', 'shift_date']);
            $table->index(['branch_id', 'shift_date']);
            $table->index('status');
        });

        // Shift break records
        Schema::create('shift_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_shift_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->enum('type', ['meal', 'rest', 'personal'])->default('rest');
            $table->timestamps();
        });

        // Daily shift summary (aggregated after close)
        Schema::create('shift_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_shift_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->unsignedInteger('invoice_count')->default(0);
            $table->decimal('cash_collected', 14, 2)->default(0);
            $table->decimal('card_collected', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('cash_difference', 14, 2)->default(0);
            $table->text('cashier_note')->nullable();
            $table->text('supervisor_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_summaries');
        Schema::dropIfExists('shift_breaks');
        Schema::dropIfExists('employee_shifts');
        Schema::dropIfExists('shift_templates');
    }
};
