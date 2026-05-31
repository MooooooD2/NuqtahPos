<?php

// ═══════════════════════════════════════════════════════════════════════════
// Phase 2 — Shift Management API
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'throttle:60,1'])->prefix('shifts')->name('api.shifts.')->group(function () {
    Route::get('/current', [App\Http\Controllers\ShiftController::class, 'current'])->name('current');
    Route::get('/history', [App\Http\Controllers\ShiftController::class, 'history'])->name('history');
    Route::post('/clock-in', [App\Http\Controllers\ShiftController::class, 'clockIn'])->name('clock-in');
    Route::post('/clock-out', [App\Http\Controllers\ShiftController::class, 'clockOut'])->name('clock-out');
    Route::post('/break/start', [App\Http\Controllers\ShiftController::class, 'startBreak'])->name('break.start');
    Route::post('/break/end', [App\Http\Controllers\ShiftController::class, 'endBreak'])->name('break.end');
    Route::get('/active', [App\Http\Controllers\ShiftController::class, 'active'])->name('active');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 4 — White Label API
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:manage_white_label', 'throttle:30,1'])->prefix('white-label')->name('api.wl.')->group(function () {
    Route::post('/', [App\Http\Controllers\WhiteLabelController::class, 'update'])->name('update');
    Route::put('/', [App\Http\Controllers\WhiteLabelController::class, 'update'])->name('update.put');
    Route::post('/domain', [App\Http\Controllers\WhiteLabelController::class, 'setCustomDomain'])->name('domain');
    Route::post('/domain/verify', [App\Http\Controllers\WhiteLabelController::class, 'verifyDomain'])->name('domain.verify');
    Route::post('/verify-domain', [App\Http\Controllers\WhiteLabelController::class, 'verifyDomain'])->name('domain.verify2');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 9 — Push Notifications API
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'throttle:30,1'])->prefix('notifications')->name('api.notifications.')->group(function () {
    Route::get('/', fn () => response()->json(auth()->user()->notifications()->latest()->take(30)->get()))->name('index');
    Route::post('/push-token', [App\Http\Controllers\Mobile\MobileAuthController::class, 'updatePushToken'])->name('push-token');
    Route::post('/read-all', function () {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    })->name('read-all');
    Route::post('/read/{id}', function (string $id) {
        auth()->user()->notifications()->findOrFail($id)->markAsRead();

        return response()->json(['success' => true]);
    })->name('read');
    Route::delete('/push-token', function (Illuminate\Http\Request $req) {
        $req->validate(['fcm_token' => 'required|string']);
        app(App\Services\PushNotificationService::class)->removeToken(auth()->user(), $req->fcm_token);

        return response()->json(['success' => true]);
    })->name('push-token.delete');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 10 — HR API
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'throttle:60,1'])->prefix('hr')->name('api.hr.')->group(function () {

    // ── Employees ─────────────────────────────────────────────────────────
    Route::middleware('permission:manage_settings')->group(function () {

        // List employees with their active salary structure
        Route::get('/employees', function (Illuminate\Http\Request $req) {
            $q = DB::table('users')
                ->leftJoin('branches', 'branches.id', '=', 'users.branch_id')
                ->leftJoin('salary_structures', function ($j) {
                    $j->on('salary_structures.user_id', '=', 'users.id')
                        ->where('salary_structures.is_active', true);
                })
                ->select(
                    'users.id',
                    'users.full_name',
                    'users.username',
                    'users.role',
                    'users.is_active',
                    'users.branch_id',
                    'branches.name as branch_name',
                    'salary_structures.id as salary_id',
                    'salary_structures.basic_salary',
                    'salary_structures.housing_allowance',
                    'salary_structures.transport_allowance',
                    'salary_structures.meal_allowance',
                    'salary_structures.other_allowances',
                    'salary_structures.overtime_rate_multiplier',
                    'salary_structures.currency_code',
                    'salary_structures.effective_from',
                )
                ->whereNull('users.deleted_at');

            if ($req->branch_id) {
                $q->where('users.branch_id', $req->branch_id);
            }
            if ($req->role) {
                $q->where('users.role', $req->role);
            }
            if ($req->status === 'active') {
                $q->where('users.is_active', true);
            }
            if ($req->status === 'inactive') {
                $q->where('users.is_active', false);
            }
            if ($req->search) {
                $q->where(fn ($w) => $w->where('users.full_name', 'like', '%' . $req->search . '%')
                    ->orWhere('users.username', 'like', '%' . $req->search . '%'));
            }

            $employees = $q->orderBy('users.full_name')->get();

            return response()->json(['employees' => $employees]);
        })->name('employees.index');

        // Get one employee's leave summary
        Route::get('/employees/{id}/leaves', function (int $id) {
            $year = date('Y');
            $reqs = DB::table('leave_requests')->where('user_id', $id)
                ->orderByDesc('created_at')->take(20)->get();
            $balance = [
                'annual_taken' => (int) DB::table('leave_requests')->where('user_id', $id)
                    ->whereYear('starts_at', $year)->where('status', 'approved')
                    ->where('leave_type', 'annual')->sum('days_count'),
                'sick_taken' => (int) DB::table('leave_requests')->where('user_id', $id)
                    ->whereYear('starts_at', $year)->where('status', 'approved')
                    ->where('leave_type', 'sick')->sum('days_count'),
                'annual_allowed' => (int) config('hr.leave_days.annual', 21),
                'sick_allowed' => (int) config('hr.leave_days.sick', 10),
            ];

            return response()->json(['requests' => $reqs, 'balance' => $balance]);
        })->name('employees.leaves');

        // Save / update salary structure for an employee
        Route::post('/employees/{id}/salary', function (Illuminate\Http\Request $req, int $id) {
            $data = $req->validate([
                'basic_salary' => 'required|numeric|min:0',
                'housing_allowance' => 'nullable|numeric|min:0',
                'transport_allowance' => 'nullable|numeric|min:0',
                'meal_allowance' => 'nullable|numeric|min:0',
                'other_allowances' => 'nullable|numeric|min:0',
                'overtime_rate_multiplier' => 'nullable|numeric|min:1|max:5',
                'currency_code' => 'nullable|string|size:3',
                'effective_from' => 'nullable|date',
            ]);

            // Deactivate old active structure
            DB::table('salary_structures')
                ->where('user_id', $id)
                ->where('is_active', true)
                ->update(['is_active' => false, 'effective_to' => now()->toDateString(), 'updated_at' => now()]);

            // Insert new active structure
            $newId = DB::table('salary_structures')->insertGetId(array_merge([
                'user_id' => $id,
                'housing_allowance' => 0,
                'transport_allowance' => 0,
                'meal_allowance' => 0,
                'other_allowances' => 0,
                'overtime_rate_multiplier' => 1.5,
                'currency_code' => 'EGP',
                'effective_from' => now()->toDateString(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_filter($data, fn ($v) => $v !== null)));

            return response()->json(['success' => true, 'id' => $newId]);
        })->name('employees.salary.save');

        // Toggle active status
        Route::patch('/employees/{id}/toggle', function (int $id) {
            $user = DB::table('users')->where('id', $id)->first();
            if (! $user) {
                return response()->json(['success' => false], 404);
            }
            DB::table('users')->where('id', $id)->update(['is_active' => ! $user->is_active, 'updated_at' => now()]);

            return response()->json(['success' => true, 'is_active' => ! $user->is_active]);
        })->name('employees.toggle');

        // Attendance history for one employee (last 30 records)
        Route::get('/employees/{id}/attendance', function (int $id) {
            $records = DB::table('attendance_records')
                ->leftJoin('branches', 'branches.id', '=', 'attendance_records.branch_id')
                ->select('attendance_records.*', 'branches.name as branch_name')
                ->where('attendance_records.user_id', $id)
                ->orderByDesc('attendance_records.work_date')
                ->take(30)
                ->get();

            return response()->json(['records' => $records]);
        })->name('employees.attendance');

        // Payroll history for one employee (last 12 slips)
        Route::get('/employees/{id}/payroll', function (int $id) {
            $slips = DB::table('payroll_slips')
                ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_slips.payroll_run_id')
                ->select('payroll_slips.*', 'payroll_runs.year', 'payroll_runs.month', 'payroll_runs.status as run_status')
                ->where('payroll_slips.user_id', $id)
                ->orderByDesc('payroll_runs.year')
                ->orderByDesc('payroll_runs.month')
                ->take(12)
                ->get();

            return response()->json(['slips' => $slips]);
        })->name('employees.payroll');
    });

    // ── HR Summary (requires HR permission) ──────────────────────────────
    Route::get('/summary', function () {
        $totalEmp = DB::table('users')->whereNull('deleted_at')->where('is_active', true)->count();
        $today = date('Y-m-d');
        $todayPresent = DB::table('attendance_records')->whereDate('work_date', $today)->where('status', 'present')->count();
        $todayTotal = DB::table('attendance_records')->whereDate('work_date', $today)->count();
        $pending = DB::table('leave_requests')->where('status', 'pending')->count();
        $lastRun = DB::table('payroll_runs')->orderByDesc('year')->orderByDesc('month')->first();

        return response()->json([
            'total_employees' => $totalEmp,
            'today_present' => $todayPresent,
            'today_total' => $todayTotal,
            'pending_leaves' => $pending,
            'last_payroll' => $lastRun ? [
                'year' => $lastRun->year,
                'month' => $lastRun->month,
                'status' => $lastRun->status,
            ] : null,
        ]);
    })->middleware('permission:manage_hr')->name('hr.summary');

    // ── Attendance ────────────────────────────────────────────────────────
    Route::get('/attendance', function (Illuminate\Http\Request $req) {
        $q = DB::table('attendance_records')
            ->leftJoin('users', 'users.id', '=', 'attendance_records.user_id')
            ->leftJoin('branches', 'branches.id', '=', 'attendance_records.branch_id')
            ->select(
                'attendance_records.*',
                'users.full_name as user_name',
                'users.username as user_username',
                'branches.name as branch_name',
            );
        if ($req->date) {
            $q->whereDate('attendance_records.work_date', $req->date);
        }
        if ($req->branch_id) {
            $q->where('attendance_records.branch_id', $req->branch_id);
        }
        if ($req->status === 'checked_out') {
            // Virtual status: present/late/half_day with check_out filled
            $q->whereNotNull('attendance_records.check_out')
                ->whereIn('attendance_records.status', ['present', 'late', 'half_day', 'remote']);
        } elseif ($req->status === 'working_now') {
            // Virtual status: clocked in but not yet out
            $q->whereNotNull('attendance_records.check_in')
                ->whereNull('attendance_records.check_out')
                ->whereIn('attendance_records.status', ['present', 'late', 'remote']);
        } elseif ($req->status) {
            $q->where('attendance_records.status', $req->status);
        }
        $records = $q->orderByDesc('attendance_records.work_date')->take(200)->get()
            ->map(fn ($r) => array_merge((array) $r, [
                'user' => ['name' => $r->user_name, 'username' => $r->user_username],
                'branch' => ['name' => $r->branch_name],
                'has_checked_out' => ! is_null($r->check_out),
                'is_working_now' => ! is_null($r->check_in) && is_null($r->check_out),
            ]));

        return response()->json(['records' => $records]);
    })->name('attendance');

    // Manual check-in / check-out from HR admin panel
    Route::post('/attendance/checkin', function (Illuminate\Http\Request $req) {
        $req->validate([
            'user_id' => 'required|integer|exists:tenant.users,id',
            'work_date' => 'required|date',
            'check_in' => 'required|date_format:H:i',
            'branch_id' => 'nullable|integer|exists:tenant.branches,id',
            'notes' => 'nullable|string|max:500',
        ]);
        DB::table('attendance_records')->updateOrInsert(
            ['user_id' => $req->user_id, 'work_date' => $req->work_date],
            [
                'branch_id' => $req->branch_id,
                'check_in' => $req->work_date . ' ' . $req->check_in . ':00',
                'check_out' => null,
                'status' => 'present',
                'notes' => $req->notes,
                'check_in_method' => 'manual',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    })->middleware('permission:manage_hr')->name('attendance.checkin');

    // Manual check-out from HR admin panel
    Route::post('/attendance/checkout', function (Illuminate\Http\Request $req) {
        $req->validate([
            'user_id' => 'required|integer|exists:tenant.users,id',
            'work_date' => 'required|date',
            'check_out' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:500',
        ]);
        $record = DB::table('attendance_records')
            ->where('user_id', $req->user_id)
            ->whereDate('work_date', $req->work_date)
            ->first();
        if (! $record) {
            return response()->json(['success' => false, 'message' => 'No check-in record found for this date.'], 422);
        }
        $checkIn = Carbon\Carbon::parse($record->check_in);
        $checkOut = Carbon\Carbon::parse($req->work_date . ' ' . $req->check_out . ':00');
        $hours = round($checkIn->diffInMinutes($checkOut) / 60, 2);
        $status = $hours >= 4 ? ($record->status === 'late' ? 'late' : 'present') : 'half_day';
        DB::table('attendance_records')
            ->where('user_id', $req->user_id)
            ->whereDate('work_date', $req->work_date)
            ->update([
                'check_out' => $req->work_date . ' ' . $req->check_out . ':00',
                'hours_worked' => $hours,
                'status' => $status,
                'notes' => $req->notes ?? $record->notes,
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true, 'hours_worked' => $hours, 'status' => $status]);
    })->middleware('permission:manage_hr')->name('attendance.checkout');

    Route::get('/attendance/export', function (Illuminate\Http\Request $req) {
        return response()->json(['url' => null, 'message' => 'Export feature coming soon']);
    })->name('attendance.export');

    // ── Shift Schedule ────────────────────────────────────────────────────
    Route::get('/shifts/schedule', function (Illuminate\Http\Request $req) {
        $weekStart = $req->week_start
            ? Carbon\Carbon::parse($req->week_start)->startOfDay()
            : Carbon\Carbon::now()->startOfWeek(Carbon\Carbon::SATURDAY);

        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $shifts = DB::table('employee_shifts')
            ->leftJoin('users', 'users.id', '=', 'employee_shifts.user_id')
            ->leftJoin('branches', 'branches.id', '=', 'employee_shifts.branch_id')
            ->leftJoin('shift_templates', 'shift_templates.id', '=', 'employee_shifts.shift_template_id')
            ->select(
                'employee_shifts.*',
                'users.full_name as user_name',
                'branches.name as branch_name',
                'shift_templates.name as template_name',
                'shift_templates.start_time',
                'shift_templates.end_time',
            )
            ->whereBetween('employee_shifts.shift_date', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        if ($req->branch_id) {
            $shifts->where('employee_shifts.branch_id', $req->branch_id);
        }

        $rows = $shifts->orderBy('employee_shifts.shift_date')->orderBy('users.full_name')->get()
            ->map(fn ($s) => array_merge((array) $s, [
                'user' => ['name' => $s->user_name],
                'branch' => ['name' => $s->branch_name],
                'template' => ['name' => $s->template_name, 'start_time' => $s->start_time, 'end_time' => $s->end_time],
            ]));

        // Build week days array
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $weekStart->copy()->addDays($i)->toDateString();
        }

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'days' => $days,
            'shifts' => $rows,
        ]);
    })->middleware('permission:manage_hr')->name('shifts.schedule');

    // Assign a shift (schedule an employee)
    Route::post('/shifts/schedule', function (Illuminate\Http\Request $req) {
        $req->validate([
            'user_id' => 'required|integer|exists:tenant.users,id',
            'shift_date' => 'required|date',
            'shift_template_id' => 'nullable|integer|exists:tenant.shift_templates,id',
            'branch_id' => 'nullable|integer|exists:tenant.branches,id',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check for existing shift on this date
        $exists = DB::table('employee_shifts')
            ->where('user_id', $req->user_id)
            ->whereDate('shift_date', $req->shift_date)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Employee already has a shift on this date.'], 422);
        }

        $id = DB::table('employee_shifts')->insertGetId([
            'user_id' => $req->user_id,
            'branch_id' => $req->branch_id,
            'shift_template_id' => $req->shift_template_id,
            'shift_date' => $req->shift_date,
            'status' => 'scheduled',
            'notes' => $req->notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    })->middleware('permission:manage_hr')->name('shifts.schedule.store');

    // Shift templates list
    Route::get('/shifts/templates', function () {
        $templates = DB::table('shift_templates')->where('is_active', true)->orderBy('name')->get();

        return response()->json(['templates' => $templates]);
    })->name('shifts.templates');

    // ── Payroll ───────────────────────────────────────────────────────────
    Route::middleware('permission:manage_settings')->group(function () {
        Route::get('/payroll/runs', function () {
            $runs = DB::table('payroll_runs')
                ->leftJoin('branches', 'branches.id', '=', 'payroll_runs.branch_id')
                ->select('payroll_runs.*', 'branches.name as branch_name')
                ->orderByDesc('payroll_runs.year')->orderByDesc('payroll_runs.month')
                ->take(50)->get()
                ->map(fn ($r) => array_merge((array) $r, [
                    'branch' => ['name' => $r->branch_name],
                    'employee_count' => DB::table('payroll_slips')->where('payroll_run_id', $r->id)->count(),
                ]));

            return response()->json(['runs' => $runs]);
        })->name('payroll.runs');

        Route::post('/payroll/generate', function (Illuminate\Http\Request $req) {
            $req->validate(['year' => 'required|integer', 'month' => 'required|integer|min:1|max:12']);

            try {
                $run = app(App\Services\PayrollService::class)->generateRun((int) $req->year, (int) $req->month, $req->branch_id);

                return response()->json(['success' => true, 'run' => $run]);
            } catch (Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        })->name('payroll.generate');

        Route::get('/payroll/runs/{id}/slips', function (int $id) {
            $slips = DB::table('payroll_slips')
                ->leftJoin('users', 'users.id', '=', 'payroll_slips.user_id')
                ->select('payroll_slips.*', 'users.full_name as user_name')
                ->where('payroll_slips.payroll_run_id', $id)
                ->get()->map(fn ($s) => array_merge((array) $s, ['user' => ['name' => $s->user_name]]));

            return response()->json(['slips' => $slips]);
        })->name('payroll.slips');

        Route::post('/payroll/runs/{id}/approve', function (int $id) {
            DB::table('payroll_runs')->where('id', $id)->update(['status' => 'approved', 'updated_at' => now()]);

            return response()->json(['success' => true]);
        })->name('payroll.approve');

        Route::post('/payroll/runs/{id}/mark-paid', function (int $id) {
            DB::table('payroll_runs')->where('id', $id)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);

            return response()->json(['success' => true]);
        })->name('payroll.mark-paid');

        Route::get('/payroll/slips/{id}/print', function (int $id) {
            $slip = DB::table('payroll_slips')
                ->leftJoin('users', 'users.id', '=', 'payroll_slips.user_id')
                ->select('payroll_slips.*', 'users.full_name as user_name')
                ->where('payroll_slips.id', $id)->first();
            if (! $slip) {
                abort(404);
            }

            return response()->json(['slip' => $slip]);
        })->name('payroll.slip.print');
    });

    // ── Leaves ────────────────────────────────────────────────────────────
    Route::get('/leaves', function (Illuminate\Http\Request $req) {
        $isAdmin = auth()->user()->hasAnyPermission(['manage_settings', 'manage_roles']);
        $q = DB::table('leave_requests')
            ->leftJoin('users', 'users.id', '=', 'leave_requests.user_id')
            ->select('leave_requests.*', 'users.full_name as user_name');
        if (! $isAdmin) {
            $q->where('leave_requests.user_id', auth()->id());
        }
        if ($req->status) {
            $q->where('leave_requests.status', $req->status);
        }
        if ($req->type) {
            $q->where('leave_requests.leave_type', $req->type);
        }
        $rows = $q->orderByDesc('leave_requests.created_at')->take(100)->get()
            ->map(fn ($r) => array_merge((array) $r, ['user' => ['name' => $r->user_name]]));

        return response()->json(['requests' => $rows]);
    })->name('leaves.index');

    Route::post('/leaves', function (Illuminate\Http\Request $req) {
        $isAdmin = auth()->user()->hasAnyPermission(['manage_settings', 'manage_roles']);

        $req->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'leave_type' => 'required|in:annual,sick,unpaid',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'reason' => 'nullable|string|max:500',
        ]);

        // Admins can submit on behalf of any employee; others always submit for themselves
        $targetUserId = ($isAdmin && $req->user_id) ? (int) $req->user_id : auth()->id();

        $days = Carbon\Carbon::parse($req->starts_at)->diffInDays(Carbon\Carbon::parse($req->ends_at)) + 1;
        $id = DB::table('leave_requests')->insertGetId([
            'user_id' => $targetUserId,
            'leave_type' => $req->leave_type,
            'starts_at' => $req->starts_at,
            'ends_at' => $req->ends_at,
            'days_count' => $days,
            'reason' => $req->reason,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id]);
    })->name('leaves.store');

    Route::post('/leaves/{id}/approve', function (int $id) {
        DB::table('leave_requests')->where('id', $id)->update(['status' => 'approved', 'approved_by' => auth()->id(), 'updated_at' => now()]);

        return response()->json(['success' => true]);
    })->middleware('permission:manage_settings')->name('leaves.approve');

    Route::post('/leaves/{id}/reject', function (int $id) {
        DB::table('leave_requests')->where('id', $id)->update(['status' => 'rejected', 'approved_by' => auth()->id(), 'updated_at' => now()]);

        return response()->json(['success' => true]);
    })->middleware('permission:manage_settings')->name('leaves.reject');

    Route::post('/leaves/{id}/cancel', function (int $id) {
        DB::table('leave_requests')->where('id', $id)
            ->where('user_id', auth()->id())->where('status', 'pending')
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        return response()->json(['success' => true]);
    })->name('leaves.cancel');

    Route::get('/leaves/balance', function () {
        $userId = auth()->id();
        $year = date('Y');
        $taken = DB::table('leave_requests')->where('user_id', $userId)
            ->whereYear('starts_at', $year)->where('status', 'approved')
            ->sum('days_count');
        $annual = max(0, (int) config('hr.leave_days.annual', 21) - (int) DB::table('leave_requests')
            ->where('user_id', $userId)->whereYear('starts_at', $year)
            ->where('status', 'approved')->where('leave_type', 'annual')->sum('days_count'));
        $sick = max(0, (int) config('hr.leave_days.sick', 10) - (int) DB::table('leave_requests')
            ->where('user_id', $userId)->whereYear('starts_at', $year)
            ->where('status', 'approved')->where('leave_type', 'sick')->sum('days_count'));

        return response()->json(['balance' => ['annual_remaining' => $annual, 'sick_remaining' => $sick, 'total_taken' => $taken]]);
    })->name('leaves.balance');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 10 — Multi-Currency API
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'throttle:60,1'])->prefix('currencies')->name('api.currencies.')->group(function () {
    Route::get('/', fn () => response()->json(['currencies' => app(App\Services\CurrencyService::class)->all()]))->name('index');

    Route::middleware('permission:manage_settings')->group(function () {
        Route::post('/', function (Illuminate\Http\Request $req) {
            $req->validate(['code' => 'required|string|size:3|unique:currencies,code', 'name' => 'required|string', 'symbol' => 'nullable|string', 'exchange_rate' => 'required|numeric|min:0', 'is_base' => 'boolean']);
            if ($req->boolean('is_base')) {
                DB::table('currencies')->update(['is_base' => false]);
            }
            $id = DB::table('currencies')->insertGetId(['code' => strtoupper($req->code), 'name' => $req->name, 'symbol' => $req->symbol, 'exchange_rate' => $req->exchange_rate, 'is_base' => $req->boolean('is_base'), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            Cache::forget('currencies.active');
            Cache::forget('currencies.rates');

            return response()->json(['success' => true, 'id' => $id]);
        })->name('store');

        Route::put('/{code}', function (Illuminate\Http\Request $req, string $code) {
            $req->validate(['exchange_rate' => 'required|numeric|min:0']);
            DB::table('currencies')->where('code', strtoupper($code))->update(['exchange_rate' => $req->exchange_rate, 'rate_updated_at' => now(), 'updated_at' => now()]);
            Cache::forget('currencies.active');
            Cache::forget('currencies.rates');

            return response()->json(['success' => true]);
        })->name('update');

        Route::patch('/{code}/toggle', function (Illuminate\Http\Request $req, string $code) {
            DB::table('currencies')->where('code', strtoupper($code))->update(['is_active' => $req->boolean('is_active'), 'updated_at' => now()]);
            Cache::forget('currencies.active');
            Cache::forget('currencies.rates');

            return response()->json(['success' => true]);
        })->name('toggle');

        Route::delete('/{code}', function (string $code) {
            $upper = strtoupper($code);
            $deleted = DB::table('currencies')
                ->where('code', $upper)
                ->where('is_base', false)
                ->delete();
            if (! $deleted) {
                return response()->json(['success' => false, 'message' => __('pos.base_currency') . ' cannot be deleted'], 422);
            }
            Cache::forget('currencies.active');
            Cache::forget('currencies.rates');

            return response()->json(['success' => true]);
        })->name('destroy');

        Route::post('/update-rates', function () {
            try {
                $ok = app(App\Services\CurrencyService::class)->updateRates();

                return response()->json(['success' => $ok, 'message' => $ok ? __('pos.update_rates') : 'Failed to fetch rates']);
            } catch (Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        })->name('update-rates');
    });

    Route::post('/convert', function (Illuminate\Http\Request $req) {
        $req->validate(['amount' => 'required|numeric', 'from' => 'required|string|size:3', 'to' => 'required|string|size:3']);

        return response()->json(['result' => app(App\Services\CurrencyService::class)->convert((float) $req->amount, $req->from, $req->to)]);
    })->name('convert');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 10 — Franchise Royalties API
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['auth', 'permission:view_reports', 'throttle:30,1'])->prefix('franchise')->name('api.franchise.')->group(function () {

    Route::get('/agreements', function () {
        $rows = DB::table('franchise_agreements')
            ->select('franchise_agreements.*')
            ->orderByDesc('created_at')->get()
            ->map(fn ($a) => array_merge((array) $a, ['franchisee_name' => 'Tenant #' . $a->franchisee_tenant_id]));

        return response()->json(['agreements' => $rows]);
    })->name('agreements');

    Route::get('/statements', function (Illuminate\Http\Request $req) {
        $q = DB::table('royalty_statements')
            ->orderByDesc('period_year')->orderByDesc('period_month');
        if ($req->year) {
            $q->where('period_year', $req->year);
        }
        if ($req->month) {
            $q->where('period_month', $req->month);
        }
        if ($req->status) {
            $q->where('status', $req->status);
        }
        $stmts = $q->take(100)->get()
            ->map(fn ($s) => array_merge((array) $s, ['franchisee_name' => 'Tenant #' . $s->franchisee_tenant_id]));

        $count = DB::table('franchise_agreements')->count();

        return response()->json(['statements' => $stmts, 'agreements_count' => $count]);
    })->name('statements');

    Route::post('/statements/generate', function (Illuminate\Http\Request $req) {
        $req->validate(['year' => 'required|integer', 'month' => 'required|integer|min:1|max:12']);

        try {
            $stmts = app(App\Services\FranchiseRoyaltyService::class)->generateMonthlyStatements((int) $req->year, (int) $req->month);

            return response()->json(['success' => true, 'message' => count($stmts) . ' statement(s) generated', 'count' => count($stmts)]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    })->name('statements.generate');

    Route::post('/statements/{id}/payment', function (Illuminate\Http\Request $req, int $id) {
        $req->validate(['amount' => 'required|numeric|min:0.01']);

        try {
            app(App\Services\FranchiseRoyaltyService::class)->recordPayment($id, (float) $req->amount);

            return response()->json(['success' => true]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    })->name('statements.payment');

    Route::get('/statements/{id}/pdf', function (int $id) {
        $stmt = DB::table('royalty_statements')->where('id', $id)->firstOrFail();

        return response()->json(['statement' => $stmt, 'message' => 'PDF generation coming soon']);
    })->name('statements.pdf');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 11 — Kiosk API (public, no auth)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware(['throttle:60,1'])->prefix('kiosk')->name('api.kiosk.')->group(function () {
    Route::get('/products', [App\Http\Controllers\KioskController::class, 'products'])->name('products');
    Route::post('/checkout', [App\Http\Controllers\KioskController::class, 'checkout'])->middleware('throttle:10,1')->name('checkout');
});

// ═══════════════════════════════════════════════════════════════════════════
// Phase 11 — Mobile API v1 (Laravel Sanctum token auth)
// ═══════════════════════════════════════════════════════════════════════════
Route::prefix('v1')->name('api.v1.')->group(function () {

    // Public: login
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/login', [App\Http\Controllers\Mobile\MobileAuthController::class, 'login'])->name('login');
    });

    // Authenticated mobile endpoints
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::post('/auth/logout', [App\Http\Controllers\Mobile\MobileAuthController::class, 'logout'])->name('logout');
        Route::get('/auth/me', [App\Http\Controllers\Mobile\MobileAuthController::class, 'me'])->name('me');
        Route::post('/auth/push-token', [App\Http\Controllers\Mobile\MobileAuthController::class, 'updatePushToken'])->name('push-token');

        // Staff app
        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/dashboard', [App\Http\Controllers\Mobile\StaffMobileController::class, 'dashboard'])->name('dashboard');
            Route::get('/shift', [App\Http\Controllers\Mobile\StaffMobileController::class, 'currentShift'])->name('shift');
            Route::post('/shift/clock-in', [App\Http\Controllers\Mobile\StaffMobileController::class, 'clockIn'])->name('shift.clock-in');
            Route::post('/shift/clock-out', [App\Http\Controllers\Mobile\StaffMobileController::class, 'clockOut'])->name('shift.clock-out');
            Route::get('/inventory', [App\Http\Controllers\Mobile\StaffMobileController::class, 'inventory'])->name('inventory');
            Route::get('/inventory/{barcode}', [App\Http\Controllers\Mobile\StaffMobileController::class, 'scanBarcode'])->name('barcode');
            Route::get('/kitchen/orders', [App\Http\Controllers\Mobile\StaffMobileController::class, 'kitchenOrders'])->name('kitchen.orders');
            Route::patch('/kitchen/orders/{id}/status', [App\Http\Controllers\Mobile\StaffMobileController::class, 'updateKitchenOrder'])->name('kitchen.status');
            Route::post('/pos/sale', [App\Http\Controllers\Mobile\StaffMobileController::class, 'quickSale'])->name('pos.sale');
        });

        // Customer app
        Route::prefix('customer')->name('customer.')->group(function () {
            Route::get('/profile', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'profile'])->name('profile');
            Route::get('/orders', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'orders'])->name('orders');
            Route::get('/orders/{id}', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'orderDetail'])->name('order');
            Route::get('/promotions', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'promotions'])->name('promotions');
            Route::get('/loyalty', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'loyalty'])->name('loyalty');
            Route::get('/menu', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'menu'])->name('menu');
            Route::post('/qr-order', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'placeQrOrder'])->name('qr-order');
            Route::get('/notifications', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'notifications'])->name('notifications');
            Route::post('/notifications/{id}/read', [App\Http\Controllers\Mobile\CustomerMobileController::class, 'markRead'])->name('notification.read');
        });
    });
});
