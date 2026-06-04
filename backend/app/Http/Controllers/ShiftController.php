<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Phase 2 — Shift & Employee Management Controller
 */
class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $service) {}

    /** Show shift management page (admin) */
    public function index()
    {
        $this->authorize('viewAny', \App\Models\EmployeeShift::class);

        $activeShifts = $this->service->allActive();

        return view('shifts.index', compact('activeShifts'));
    }

    /** My shift page (employee) */
    public function myShift()
    {
        $shift = $this->service->activeShift(auth()->user());
        $history = $this->service->history(auth()->user(), 14);

        return view('shifts.my-shift', compact('shift', 'history'));
    }

    /** POST /api/shifts/clock-in */
    public function clockIn(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'shift_template_id' => 'nullable|exists:shift_templates,id',
        ]);

        try {
            $shift = $this->service->clockIn(auth()->user(), $request->all());

            return response()->json(['success' => true, 'shift' => $shift]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/shifts/clock-out */
    public function clockOut(Request $request): JsonResponse
    {
        $request->validate([
            'cash_collected' => 'nullable|numeric|min:0',
            'card_collected' => 'nullable|numeric|min:0',
            'cashier_note' => 'nullable|string|max:1000',
        ]);

        try {
            $shift = $this->service->clockOut(auth()->user(), $request->all());

            return response()->json(['success' => true, 'shift' => $shift->load('summary')]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /** POST /api/shifts/break/start */
    public function startBreak(Request $request): JsonResponse
    {
        $request->validate(['type' => 'nullable|in:meal,rest,personal']);

        $break = $this->service->startBreak(auth()->user(), $request->get('type', 'rest'));

        return response()->json(['success' => true, 'break' => $break]);
    }

    /** POST /api/shifts/break/end */
    public function endBreak(): JsonResponse
    {
        $break = $this->service->endBreak(auth()->user());

        return response()->json(['success' => true, 'break' => $break]);
    }

    /** GET /api/shifts/current */
    public function current(): JsonResponse
    {
        $shift = $this->service->activeShift(auth()->user());

        return response()->json(['shift' => $shift]);
    }

    /** GET /api/shifts/history */
    public function history(Request $request): JsonResponse
    {
        $history = $this->service->history(auth()->user(), (int) $request->get('days', 30));

        return response()->json(['success' => true, 'data' => $history]);
    }

    /** GET /api/shifts/active — admin: all active shifts */
    public function active(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\EmployeeShift::class);

        return response()->json(['shifts' => $this->service->allActive()]);
    }
}
