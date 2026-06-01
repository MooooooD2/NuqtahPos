<?php

namespace App\Http\Controllers;

use App\Models\FiscalPeriod;
use App\Services\PeriodClosingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalPeriodController extends Controller
{
    public function __construct(private PeriodClosingService $closingService) {}

    public function index(): JsonResponse
    {
        $periods = FiscalPeriod::with('closedBy:id,username,full_name')
            ->orderByDesc('start_date')
            ->get();

        return response()->json(['success' => true, 'data' => $periods]);
    }

    public function current(): JsonResponse
    {
        $period = FiscalPeriod::forDate(now()->toDateString());

        return response()->json($period);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage_accounting');

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $period = $this->closingService->openPeriod($data);

            return response()->json($period, 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function previewClose(FiscalPeriod $fiscalPeriod): JsonResponse
    {
        if ($fiscalPeriod->isClosed()) {
            return response()->json(['message' => __('pos.period_already_closed')], 422);
        }

        $preview = $this->closingService->previewClosingEntry($fiscalPeriod);

        return response()->json([
            'period' => $fiscalPeriod->only(['id', 'name', 'start_date', 'end_date']),
            'preview' => $preview,
        ]);
    }

    public function close(Request $request, FiscalPeriod $fiscalPeriod): JsonResponse
    {
        $this->authorize('manage_accounting');

        $data = $request->validate([
            'retained_earnings_account_id' => 'required|integer|exists:accounts,id',
        ]);

        try {
            $period = $this->closingService->closePeriod($fiscalPeriod, $data['retained_earnings_account_id']);

            return response()->json($period->load('closingEntry'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
