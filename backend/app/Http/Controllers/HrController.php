<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HrController extends Controller
{
    use ApiResponse;

    private function userName(User $u): string
    {
        return $u->full_name ?: $u->username ?: $u->email;
    }

    /* ─── Employees ─────────────────────────────────────────────────────── */

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $search  = $request->query('search', '');

        $data = User::query()
            ->when($search, fn ($q) => $q->where('username', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"))
            ->select(['id', 'username', 'full_name', 'email', 'role', 'is_active', 'created_at'])
            ->paginate($perPage);

        $employees = $data->map(fn (User $u) => [
            'id'         => $u->id,
            'name'       => $this->userName($u),
            'email'      => $u->email,
            'position'   => $u->role,
            'department' => $u->role === 'admin' ? 'Management' : ($u->role === 'cashier' ? 'Sales' : 'Operations'),
            'salary'     => '0.00',
            'status'     => $u->is_active ? 'active' : 'inactive',
            'hire_date'  => $u->created_at?->toDateString(),
        ]);

        return $this->success(['data' => $employees, 'total' => $data->total()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'nullable|email|unique:users,email',
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0',
            'status'     => 'nullable|in:active,inactive,terminated',
            'hire_date'  => 'nullable|date',
        ]);

        $base = strtolower(str_replace(' ', '.', $data['name']));
        $username = $base;
        $i = 1;
        while (User::where('username', $username)->exists()) {
            $username = $base . $i++;
        }

        $user = User::create([
            'username'  => $username,
            'full_name' => $data['name'],
            'email'     => $data['email'] ?? null,
            'phone'     => $data['phone'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'password'  => Hash::make('ChangeMe123!'),
            'role'      => in_array($data['position'] ?? '', ['admin', 'cashier', 'warehouse']) ? $data['position'] : 'cashier',
            'is_active' => ($data['status'] ?? 'active') === 'active',
        ]);

        if (isset($data['salary']) && (float) $data['salary'] > 0) {
            DB::table('salary_structures')->insert([
                'user_id'                  => $user->id,
                'basic_salary'             => (float) $data['salary'],
                'housing_allowance'        => 0,
                'transport_allowance'      => 0,
                'meal_allowance'           => 0,
                'other_allowances'         => 0,
                'overtime_rate_multiplier' => 1.5,
                'currency_code'            => 'EGP',
                'effective_from'           => now()->toDateString(),
                'is_active'                => true,
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }

        return $this->success(['data' => ['id' => $user->id, 'name' => $this->userName($user)]], 'Employee created', 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'email'      => 'sometimes|nullable|email|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0',
            'status'     => 'nullable|in:active,inactive,terminated',
            'hire_date'  => 'nullable|date',
        ]);

        $update = ['is_active' => isset($data['status']) ? $data['status'] === 'active' : $user->is_active];
        if (isset($data['name'])) $update['full_name'] = $data['name'];
        if (array_key_exists('email', $data)) $update['email'] = $data['email'];
        if (array_key_exists('phone', $data)) $update['phone'] = $data['phone'];
        if (array_key_exists('hire_date', $data)) $update['hire_date'] = $data['hire_date'];
        $user->update($update);

        if (isset($data['salary'])) {
            DB::table('salary_structures')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'effective_to' => now()->toDateString(), 'updated_at' => now()]);

            DB::table('salary_structures')->insert([
                'user_id'                  => $user->id,
                'basic_salary'             => (float) $data['salary'],
                'housing_allowance'        => 0,
                'transport_allowance'      => 0,
                'meal_allowance'           => 0,
                'other_allowances'         => 0,
                'overtime_rate_multiplier' => 1.5,
                'currency_code'            => 'EGP',
                'effective_from'           => now()->toDateString(),
                'is_active'                => true,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        return $this->success(['data' => ['id' => $user->id, 'name' => $this->userName($user)]], 'Employee updated');
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === (int) auth()->id()) {
            return $this->error('Cannot delete yourself', 422);
        }
        $user->delete();
        return $this->success([], 'Employee deleted');
    }

    public function shifts(): JsonResponse
    {
        if (class_exists(\App\Models\EmployeeShift::class)) {
            $items  = \App\Models\EmployeeShift::with('user:id,username,full_name')->latest()->paginate(30)->items();
            $shifts = collect($items)->map(fn ($s) => [
                'id'            => $s->id,
                'employee_name' => $s->user ? ($s->user->full_name ?: $s->user->username) : null,
                'start_time'    => $s->start_time,
                'end_time'      => $s->end_time,
                'status'        => $s->status,
                'total_hours'   => $s->total_hours,
            ]);
            return $this->success(['data' => $shifts]);
        }
        return $this->success(['data' => []]);
    }

    /* ─── Attendance ─────────────────────────────────────────────────────── */

    public function attendance(Request $request): JsonResponse
    {
        $date    = $request->get('date', today()->toDateString());
        $status  = $request->get('status', 'all');
        $perPage = min((int) $request->get('per_page', 30), 100);

        $query = AttendanceRecord::with('user:id,username,full_name')
            ->where('work_date', $date)
            ->when($status && $status !== 'all', function ($q) use ($status) {
                if ($status === 'working') {
                    $q->whereNotNull('check_in')->whereNull('check_out');
                } elseif ($status === 'checked_out') {
                    $q->whereNotNull('check_out');
                } elseif ($status === 'absent') {
                    $q->where('status', 'absent');
                } elseif ($status === 'late') {
                    $q->where('status', 'late');
                }
            });

        $items = $query->orderBy('check_in')->paginate($perPage)->items();

        // Fetch break minutes per user for this date from employee_shifts + shift_breaks
        $userIds = collect($items)->pluck('user_id')->unique()->filter()->values()->all();
        $breakMinutesByUser = [];
        if ($userIds) {
            $breakMinutesByUser = DB::table('employee_shifts')
                ->join('shift_breaks', 'shift_breaks.employee_shift_id', '=', 'employee_shifts.id')
                ->where('employee_shifts.shift_date', $date)
                ->whereIn('employee_shifts.user_id', $userIds)
                ->whereNotNull('shift_breaks.ended_at')
                ->groupBy('employee_shifts.user_id')
                ->pluck(DB::raw('SUM(shift_breaks.duration_minutes)'), 'employee_shifts.user_id')
                ->toArray();
        }

        $records = collect($items)->map(fn ($r) => [
            'id'              => $r->id,
            'user_name'       => $r->user ? ($r->user->full_name ?: $r->user->username) : null,
            'work_date'       => $r->work_date->toDateString(),
            'check_in'        => $r->check_in?->toDateTimeString(),
            'check_out'       => $r->check_out?->toDateTimeString(),
            'hours_worked'    => $r->hours_worked,
            'break_minutes'   => (int) ($breakMinutesByUser[$r->user_id] ?? 0) ?: null,
            'status'          => $r->check_in && ! $r->check_out ? 'working' : ($r->check_out ? 'checked_out' : $r->status),
            'notes'           => $r->notes,
            'is_working_now'  => (bool) ($r->check_in && ! $r->check_out),
            'has_checked_out' => (bool) $r->check_out,
        ]);

        return $this->success(['records' => $records]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'   => 'required|integer|exists:users,id',
            'work_date' => 'required|date',
            'check_in'  => 'required|string',
            'check_out' => 'nullable|string',
            'notes'     => 'nullable|string|max:500',
        ]);

        $checkInDt  = Carbon::parse($data['work_date'] . ' ' . $data['check_in']);
        $checkOutDt = ! empty($data['check_out'])
            ? Carbon::parse($data['work_date'] . ' ' . $data['check_out'])
            : null;

        $hoursWorked = $checkOutDt
            ? round($checkInDt->diffInMinutes($checkOutDt) / 60, 2)
            : null;

        $record = AttendanceRecord::updateOrCreate(
            ['user_id' => $data['user_id'], 'work_date' => $data['work_date']],
            [
                'check_in'        => $checkInDt,
                'check_out'       => $checkOutDt,
                'hours_worked'    => $hoursWorked,
                'status'          => 'present',
                'notes'           => $data['notes'] ?? null,
                'check_in_method' => 'manual',
            ]
        );

        return $this->success(['record' => $record], '', 201);
    }

    /* ─── Leaves ─────────────────────────────────────────────────────────── */

    public function leaves(Request $request): JsonResponse
    {
        $status  = $request->get('status', '');
        $perPage = min((int) $request->get('per_page', 20), 100);

        $requests = collect(
            LeaveRequest::with('user:id,username,full_name')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate($perPage)
                ->items()
        )->map(fn ($r) => [
            'id'         => $r->id,
            'user_name'  => $r->user ? ($r->user->full_name ?: $r->user->username) : null,
            'leave_type' => $r->leave_type,
            'starts_at'  => $r->starts_at?->toDateString(),
            'ends_at'    => $r->ends_at?->toDateString(),
            'days_count' => $r->days_count,
            'status'     => $r->status,
            'reason'     => $r->reason,
        ]);

        return $this->success(['requests' => $requests]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'leave_type' => 'required|in:annual,sick,unpaid,emergency,maternity,paternity',
            'starts_at'  => 'required|date',
            'ends_at'    => 'required|date|after_or_equal:starts_at',
            'reason'     => 'nullable|string|max:1000',
        ]);

        $days = (int) Carbon::parse($data['starts_at'])->diffInDays(Carbon::parse($data['ends_at'])) + 1;

        $leave = LeaveRequest::create([
            'user_id'    => $data['user_id'],
            'leave_type' => $data['leave_type'],
            'starts_at'  => $data['starts_at'],
            'ends_at'    => $data['ends_at'],
            'days_count' => $days,
            'reason'     => $data['reason'] ?? null,
            'status'     => 'pending',
        ]);

        return $this->success(['request' => $leave], '', 201);
    }

    public function approveLeave(int $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);
        $leave->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        return $this->success([]);
    }

    public function rejectLeave(int $id): JsonResponse
    {
        $leave = LeaveRequest::findOrFail($id);
        $leave->update(['status' => 'rejected']);
        return $this->success([]);
    }

    /* ─── Payroll ────────────────────────────────────────────────────────── */

    public function payrollRuns(): JsonResponse
    {
        $runs = PayrollRun::withCount('slips as employee_count')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'period'         => sprintf('%04d-%02d', $r->year, $r->month),
                'employee_count' => $r->employee_count,
                'gross_salary'   => $r->total_gross,
                'net_salary'     => $r->total_net,
                'status'         => $r->status,
            ]);

        return $this->success(['runs' => $runs]);
    }

    public function generatePayroll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'year'  => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $existing = PayrollRun::where('year', $data['year'])->where('month', $data['month'])->first();
        if ($existing) {
            return $this->error('Payroll run already exists for this period', 422);
        }

        $run = PayrollRun::create([
            'year'             => $data['year'],
            'month'            => $data['month'],
            'status'           => 'draft',
            'total_gross'      => 0,
            'total_deductions' => 0,
            'total_net'        => 0,
        ]);

        $activeUsers = User::where('is_active', true)->count();

        return $this->success([
            'run' => [
                'id'             => $run->id,
                'period'         => sprintf('%04d-%02d', $run->year, $run->month),
                'employee_count' => $activeUsers,
                'gross_salary'   => '0.00',
                'net_salary'     => '0.00',
                'status'         => $run->status,
            ],
        ], '', 201);
    }

    public function approvePayrollRun(int $id): JsonResponse
    {
        $run = PayrollRun::findOrFail($id);
        if ($run->status !== 'draft') {
            return $this->error('Only draft runs can be approved', 422);
        }
        $run->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        return $this->success([]);
    }

    public function markPayrollPaid(int $id): JsonResponse
    {
        $run = PayrollRun::findOrFail($id);
        if ($run->status !== 'approved') {
            return $this->error('Only approved runs can be marked as paid', 422);
        }
        $run->update(['status' => 'paid', 'paid_at' => now()]);
        return $this->success([]);
    }
}
