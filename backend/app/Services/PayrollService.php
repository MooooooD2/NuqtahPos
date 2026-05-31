<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 10 — HR: Payroll Calculation Engine
 */
class PayrollService
{
    /**
     * Generate payroll slips for all active employees for a given month.
     */
    public function generateRun(int $year, int $month, ?int $branchId = null): array
    {
        return DB::transaction(function () use ($year, $month, $branchId) {
            // Prevent duplicate run
            $existing = DB::table('payroll_runs')
                ->where('year', $year)
                ->where('month', $month)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->first();

            if ($existing && $existing->status !== 'draft') {
                throw new RuntimeException("Payroll for {$year}/{$month} has already been approved.");
            }

            // Create or reset the run
            $runId = DB::table('payroll_runs')->updateOrInsert(
                ['year' => $year, 'month' => $month, 'branch_id' => $branchId],
                ['status' => 'draft', 'updated_at' => now(), 'created_at' => now()],
            );

            $run = DB::table('payroll_runs')
                ->where('year', $year)->where('month', $month)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->first();

            // Get employees
            $employees = User::active()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->get();

            $slips = [];
            $totalGross = 0;
            $totalNet = 0;
            $totalDed = 0;

            foreach ($employees as $employee) {
                $slip = $this->calculateSlip($employee, $year, $month, $run->id);
                $slips[] = $slip;
                $totalGross += $slip['gross_salary'];
                $totalNet += $slip['net_salary'];
                $totalDed += ($slip['income_tax'] + $slip['social_insurance'] + $slip['other_deductions']
                              + $slip['absence_deduction'] + $slip['late_deduction']);
            }

            // Upsert slips
            DB::table('payroll_slips')->where('payroll_run_id', $run->id)->delete();
            foreach ($slips as $slip) {
                DB::table('payroll_slips')->insert($slip);
            }

            // Update run totals
            DB::table('payroll_runs')->where('id', $run->id)->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDed,
                'total_net' => $totalNet,
                'updated_at' => now(),
            ]);

            return [
                'run_id' => $run->id,
                'year' => $year,
                'month' => $month,
                'employees' => count($slips),
                'total_gross' => $totalGross,
                'total_net' => $totalNet,
                'slips' => $slips,
            ];
        });
    }

    /**
     * Calculate a single employee's payroll slip.
     */
    public function calculateSlip(User $employee, int $year, int $month, int $runId): array
    {
        // Get salary structure
        $structure = DB::table('salary_structures')
            ->where('user_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', "{$year}-{$month}-01")
            ->latest('effective_from')
            ->first();

        $basicSalary = (float) ($structure?->basic_salary ?? 0);
        $housingAllowance = (float) ($structure?->housing_allowance ?? 0);
        $transportAllow = (float) ($structure?->transport_allowance ?? 0);
        $mealAllow = (float) ($structure?->meal_allowance ?? 0);
        $otherAllowances = (float) ($structure?->other_allowances ?? 0);
        $otMultiplier = (float) ($structure?->overtime_rate_multiplier ?? 1.5);

        // Attendance for this month
        $attendance = $this->getMonthAttendance($employee->id, $year, $month);

        $workingDays = $this->workingDaysInMonth($year, $month);
        $absentDays = $attendance['absent_days'];
        $lateMinutes = $attendance['late_minutes'];
        $overtimeHours = $attendance['overtime_hours'];

        // Deductions
        $dailyRate = $basicSalary / max($workingDays, 1);
        $hourlyRate = $basicSalary / (max($workingDays, 1) * 8);
        $absenceDeduction = round($dailyRate * $absentDays, 2);
        $lateDeduction = round(($hourlyRate / 60) * $lateMinutes, 2);
        $overtimePay = round($hourlyRate * $otMultiplier * $overtimeHours, 2);

        $totalAllowances = $housingAllowance + $transportAllow + $mealAllow + $otherAllowances;
        $grossSalary = $basicSalary + $totalAllowances + $overtimePay;

        // Tax & social insurance (configurable — using Egypt defaults)
        $incomeTax = $this->calculateIncomeTax($grossSalary);
        $socialInsurance = $this->calculateSocialInsurance($basicSalary);

        $netSalary = max(
            0,
            $grossSalary
            - $incomeTax
            - $socialInsurance
            - $absenceDeduction
            - $lateDeduction,
        );

        return [
            'payroll_run_id' => $runId,
            'user_id' => $employee->id,
            'basic_salary' => $basicSalary,
            'total_allowances' => $totalAllowances,
            'overtime_pay' => $overtimePay,
            'bonus' => 0,
            'gross_salary' => round($grossSalary, 2),
            'income_tax' => $incomeTax,
            'social_insurance' => $socialInsurance,
            'other_deductions' => 0,
            'absence_deduction' => $absenceDeduction,
            'late_deduction' => $lateDeduction,
            'net_salary' => round($netSalary, 2),
            'working_days' => $workingDays,
            'absent_days' => $absentDays,
            'overtime_hours' => $overtimeHours,
            'currency_code' => $structure?->currency_code ?? 'EGP',
            'breakdown' => json_encode([
                'basic' => $basicSalary,
                'housing' => $housingAllowance,
                'transport' => $transportAllow,
                'meal' => $mealAllow,
                'other_allow' => $otherAllowances,
                'overtime' => $overtimePay,
                'gross' => $grossSalary,
                'tax' => $incomeTax,
                'social' => $socialInsurance,
                'absence_ded' => $absenceDeduction,
                'late_ded' => $lateDeduction,
                'net' => $netSalary,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function getMonthAttendance(int $userId, int $year, int $month): array
    {
        // ── 1. Recorded attendance ─────────────────────────────────────────
        $records = DB::table('attendance_records')
            ->where('user_id', $userId)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->get();

        $explicitAbsent = $records->where('status', 'absent')->count();
        $lateMinutes = (float) $records->sum('late_minutes');
        $overtimeHours = (float) $records->sum('overtime_hours');

        // ── 2. Approved leave requests that fall in this month ─────────────
        $monthStart = \Carbon\Carbon::create($year, $month, 1);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $leaves = DB::table('leave_requests')
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->where('starts_at', '<=', $monthEnd->toDateString())
            ->where('ends_at', '>=', $monthStart->toDateString())
            ->get();

        // Count working (non-weekend) days covered by each leave type
        $unpaidLeaveDays = 0;
        $paidLeaveDays = 0; // annual / sick — for reference only (no deduction)

        foreach ($leaves as $leave) {
            $start = \Carbon\Carbon::parse($leave->starts_at)->max($monthStart);
            $end = \Carbon\Carbon::parse($leave->ends_at)->min($monthEnd);
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                if (! $cursor->isWeekend()) {
                    if ($leave->leave_type === 'unpaid') {
                        $unpaidLeaveDays++;
                    } else {
                        $paidLeaveDays++;
                    }
                }
                $cursor->addDay();
            }
        }

        // Unpaid leave days are treated the same as absent days for deductions.
        // Paid leave days (annual / sick) are NOT deducted — employee is entitled.
        $absentDays = $explicitAbsent + $unpaidLeaveDays;

        return [
            'absent_days' => $absentDays,
            'unpaid_leave_days' => $unpaidLeaveDays,
            'paid_leave_days' => $paidLeaveDays,
            'late_minutes' => $lateMinutes,
            'overtime_hours' => $overtimeHours,
        ];
    }

    private function workingDaysInMonth(int $year, int $month): int
    {
        $start = \Carbon\Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();
        $days = 0;
        while ($start->lte($end)) {
            if (! $start->isWeekend()) {
                $days++;
            }
            $start->addDay();
        }

        return $days;
    }

    /**
     * Egypt progressive income tax brackets (2024).
     * Tweak via config or make dynamic per tenant.
     */
    private function calculateIncomeTax(float $annualGross): float
    {
        $annual = $annualGross * 12;
        $tax = 0.0;

        // EGP brackets
        if ($annual <= 15_000) {
            $tax = 0;
        } elseif ($annual <= 30_000) {
            $tax = ($annual - 15_000) * 0.10;
        } elseif ($annual <= 45_000) {
            $tax = 1_500 + ($annual - 30_000) * 0.15;
        } elseif ($annual <= 60_000) {
            $tax = 3_750 + ($annual - 45_000) * 0.20;
        } elseif ($annual <= 200_000) {
            $tax = 6_750 + ($annual - 60_000) * 0.225;
        } elseif ($annual <= 400_000) {
            $tax = 38_250 + ($annual - 200_000) * 0.25;
        } else {
            $tax = 88_250 + ($annual - 400_000) * 0.275;
        }

        return round($tax / 12, 2); // monthly tax
    }

    /**
     * Egypt social insurance: employee 11%, capped at max contribution salary.
     */
    private function calculateSocialInsurance(float $basicSalary): float
    {
        $maxBase = config('hr.social_insurance.max_base', 10_000);
        $rate = config('hr.social_insurance.employee_rate', 0.11);

        return round(min($basicSalary, $maxBase) * $rate, 2);
    }
}
