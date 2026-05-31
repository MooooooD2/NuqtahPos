<?php

namespace App\Http\Controllers;

use App\Services\AiForecastingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function __construct(private AiForecastingService $forecasting) {}

    /* ─── View ───────────────────────────────────────────────────────── */

    public function index(): \Illuminate\View\View
    {
        return view('forecasting.index');
    }

    /* ─── API ────────────────────────────────────────────────────────── */

    /**
     * Sales forecast for next N days.
     */
    public function salesForecast(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $history = (int) $request->get('history', 90);

        $days = min(max($days, 7), 90);
        $history = min(max($history, 14), 365);

        return response()->json($this->forecasting->forecastSales($days, $history));
    }

    /**
     * Product demand forecast.
     */
    public function productForecast(Request $request): JsonResponse
    {
        $topN = (int) $request->get('top', 20);
        $history = (int) $request->get('history', 60);

        return response()->json($this->forecasting->forecastProducts($topN, $history));
    }

    /**
     * Stock depletion forecast.
     */
    public function stockForecast(Request $request): JsonResponse
    {
        $history = (int) $request->get('history', 30);

        return response()->json($this->forecasting->forecastStock($history));
    }
}
