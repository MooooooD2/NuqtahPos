<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProfitReportController extends Controller
{
    use ApiResponse;

    public function __construct(private ReportService $reportService) {}

    public function byProduct(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category' => 'nullable|string|max:100',
        ]);

        return $this->success($this->reportService->profitByProduct(
            $request->only(['start_date', 'end_date', 'category']),
        ));
    }

    public function daily(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return $this->success($this->reportService->profitDaily(
            $request->only(['start_date', 'end_date']),
        ));
    }
}
