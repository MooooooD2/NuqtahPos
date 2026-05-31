<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EmployeeShift;
use App\Models\ShiftBreak;
use App\Models\ShiftSummary;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 2 — Shift & Employee Management Service
 */
class ShiftService
{
    /**
     * Open (clock in) a shift for the given user.
     * Also creates a live "present" attendance_record so HR attendance
     * shows today's clock-ins in real time.
     */
    public function clockIn(User $user, array $data = []): EmployeeShift
    {
        // Prevent double clock-in
        $active = EmployeeShift::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($active) {
            throw new RuntimeException("User already has an active shift (#{$active->id}).");
        }

        $branchId = $data['branch_id'] ?? $user->branch_id ?? null;

        $shift = EmployeeShift::create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'shift_template_id' => $data['shift_template_id'] ?? null,
            'shift_date' => today(),
            'clock_in_at' => now(),
            'status' => 'active',
            'opened_by' => auth()->id(),
            'meta' => $data['meta'] ?? null,
        ]);

        // ── Mirror to attendance_records (live, incomplete until clock-out) ──
        DB::table('attendance_records')->updateOrInsert(
            ['user_id' => $user->id, 'work_date' => today()->toDateString()],
            [
                'branch_id' => $branchId,
                'check_in' => now(),
                'check_out' => null,
                'hours_worked' => null,
                'overtime_hours' => 0,
                'late_minutes' => 0,
                'status' => 'present',
                'check_in_method' => 'shift',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return $shift;
    }

    /**
     * Clock out the currently active shift.
     */
    public function clockOut(User $user, array $data = []): EmployeeShift
    {
        $shift = EmployeeShift::where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        DB::transaction(function () use ($shift, $data) {
            // End any open break
            ShiftBreak::where('employee_shift_id', $shift->id)
                ->whereNull('ended_at')
                ->each(fn ($b) => $b->end());

            $shift->clockOut();

            // Create summary
            $sales = $this->aggregateShiftSales($shift);
            ShiftSummary::create([
                'employee_shift_id' => $shift->id,
                'total_sales' => $sales['total'],
                'invoice_count' => $sales['count'],
                'cash_collected' => $data['cash_collected'] ?? 0,
                'card_collected' => $data['card_collected'] ?? 0,
                'expected_cash' => $sales['cash_expected'],
                'cash_difference' => ($data['cash_collected'] ?? 0) - $sales['cash_expected'],
                'cashier_note' => $data['cashier_note'] ?? null,
            ]);

            // ── Mirror final data to attendance_records ──────────────────────
            $fresh = $shift->fresh();
            $hoursWorked = (float) ($fresh->hours_worked ?? 0);
            $attStatus = $hoursWorked >= 4 ? 'present' : 'half_day';

            DB::table('attendance_records')->updateOrInsert(
                [
                    'user_id' => $shift->user_id,
                    'work_date' => $fresh->shift_date->toDateString(),
                ],
                [
                    'branch_id' => $shift->branch_id,
                    'check_in' => $fresh->clock_in_at,
                    'check_out' => $fresh->clock_out_at,
                    'hours_worked' => $hoursWorked,
                    'overtime_hours' => (float) ($fresh->overtime_hours ?? 0),
                    'late_minutes' => 0,
                    'status' => $attStatus,
                    'check_in_method' => 'shift',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        });

        return $shift->fresh(['summary']);
    }

    /**
     * Start a break for the active shift.
     */
    public function startBreak(User $user, string $type = 'rest'): ShiftBreak
    {
        $shift = EmployeeShift::where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        return ShiftBreak::create([
            'employee_shift_id' => $shift->id,
            'started_at' => now(),
            'type' => $type,
        ]);
    }

    /**
     * End the current break.
     */
    public function endBreak(User $user): ShiftBreak
    {
        $shift = EmployeeShift::where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        $break = ShiftBreak::where('employee_shift_id', $shift->id)
            ->whereNull('ended_at')
            ->latest()
            ->firstOrFail();

        $break->end();

        return $break;
    }

    /**
     * Get currently active shift for a user (or null).
     */
    public function activeShift(User $user): ?EmployeeShift
    {
        return EmployeeShift::with(['template', 'breaks'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Get shift history for a user.
     */
    public function history(User $user, int $days = 30): Collection
    {
        return EmployeeShift::with(['summary', 'template', 'breaks'])
            ->where('user_id', $user->id)
            ->where('shift_date', '>=', today()->subDays($days))
            ->orderByDesc('shift_date')
            ->get();
    }

    /**
     * Admin: list all active shifts across branches.
     */
    public function allActive(): Collection
    {
        return EmployeeShift::with(['user', 'branch', 'template', 'breaks'])
            ->where('status', 'active')
            ->orderBy('clock_in_at')
            ->get();
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function aggregateShiftSales(EmployeeShift $shift): array
    {
        $invoices = \App\Models\Invoice::where('cashier_id', $shift->user_id)
            ->whereBetween('created_at', [
                $shift->clock_in_at,
                $shift->clock_out_at ?? now(),
            ])
            ->whereIn('status', ['completed', 'paid'])
            ->get();

        return [
            'total' => $invoices->sum('total'),
            'count' => $invoices->count(),
            'cash_expected' => $invoices->where('payment_method', 'cash')->sum('total'),
        ];
    }
}
