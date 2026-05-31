<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    use ApiResponse;

    public function __construct(private ReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $budgets = Budget::where('year', $request->year)
            ->when($request->month, fn ($q) => $q->where('month', $request->month))
            ->orderBy('month')
            ->orderBy('type')
            ->get();

        return $this->success(['budgets' => $budgets]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.year' => 'required|integer|min:2000|max:2100',
            'entries.*.month' => 'required|integer|min:1|max:12',
            'entries.*.type' => 'required|in:revenue,expense',
            'entries.*.category' => 'nullable|string|max:150',
            'entries.*.amount' => 'required|numeric|min:0',
            'entries.*.notes' => 'nullable|string|max:500',
        ]);

        $saved = [];
        foreach ($data['entries'] as $entry) {
            $saved[] = Budget::updateOrCreate(
                [
                    'year' => $entry['year'],
                    'month' => $entry['month'],
                    'type' => $entry['type'],
                    'category' => $entry['category'] ?? null,
                ],
                [
                    'amount' => $entry['amount'],
                    'notes' => $entry['notes'] ?? null,
                ],
            );
        }

        return $this->success(['saved' => count($saved)], '', 200);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        $budget->delete();

        return $this->success([], __('pos.budget_deleted'));
    }

    public function report(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $data = $this->reportService->budgetVsActual(
            (int) $request->year,
            $request->month ? (int) $request->month : null,
        );

        return $this->success($data);
    }
}
