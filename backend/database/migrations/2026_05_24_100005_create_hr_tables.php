<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 — HR Module: Attendance, Leaves & Payroll
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Attendance ────────────────────────────────────────────────────────
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in')->nullable();
            $table->timestamp('check_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'remote', 'holiday'])->default('present');
            $table->decimal('late_minutes', 6, 0)->default(0);
            $table->text('notes')->nullable();
            $table->string('check_in_method')->default('manual');   // manual, qr, biometric, gps
            $table->json('location')->nullable();                    // {lat, lng}
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['branch_id', 'work_date']);
        });

        // ── Leave Requests ────────────────────────────────────────────────────
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // Annual, Sick, Unpaid ...
            $table->unsignedSmallInteger('days_allowed')->default(21);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days_count')->default(1);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // ── Payroll ───────────────────────────────────────────────────────────
        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('housing_allowance', 14, 2)->default(0);
            $table->decimal('transport_allowance', 14, 2)->default(0);
            $table->decimal('meal_allowance', 14, 2)->default(0);
            $table->decimal('other_allowances', 14, 2)->default(0);
            $table->decimal('overtime_rate_multiplier', 5, 2)->default(1.5);   // 1.5x
            $table->string('currency_code', 3)->default('EGP');
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');           // 1-12
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'branch_id']);
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic_salary', 14, 2);
            $table->decimal('total_allowances', 14, 2)->default(0);
            $table->decimal('overtime_pay', 14, 2)->default(0);
            $table->decimal('bonus', 14, 2)->default(0);
            $table->decimal('gross_salary', 14, 2);
            $table->decimal('income_tax', 14, 2)->default(0);
            $table->decimal('social_insurance', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('absence_deduction', 14, 2)->default(0);
            $table->decimal('late_deduction', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2);
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->json('breakdown')->nullable();           // detailed earnings/deductions
            $table->string('currency_code', 3)->default('EGP');
            $table->timestamps();

            $table->index(['payroll_run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_records');
    }
};
