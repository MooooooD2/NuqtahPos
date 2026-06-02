<?php

namespace App\Http\Controllers;

use App\Services\AiForecastingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    use ApiResponse;

    public function __construct(private AiForecastingService $forecasting) {}

    /* ─── View ───────────────────────────────────────────────────────── */

    public function index(): \Illuminate\View\View
    {
        return view('forecasting.index');
    }

    /* ─── API ────────────────────────────────────────────────────────── */

    /**
     * Sales forecast for next N days.
     * Accepts: days|forecast_days, history|historical_days
     */
    public function salesForecast(Request $request): JsonResponse
    {
        $days    = (int) ($request->get('forecast_days') ?? $request->get('days', 30));
        $history = (int) ($request->get('historical_days') ?? $request->get('history', 90));

        $days    = min(max($days, 7), 90);
        $history = min(max($history, 14), 365);

        return $this->success(['data' => $this->forecasting->forecastSales($days, $history)]);
    }

    /**
     * Product demand forecast.
     */
    public function productForecast(Request $request): JsonResponse
    {
        $topN    = (int) $request->get('top', 20);
        $history = (int) ($request->get('historical_days') ?? $request->get('history', 60));

        return $this->success(['data' => $this->forecasting->forecastProducts($topN, $history)]);
    }

    /**
     * Stock depletion forecast.
     */
    public function stockForecast(Request $request): JsonResponse
    {
        $history = (int) ($request->get('historical_days') ?? $request->get('history', 30));

        return $this->success(['data' => $this->forecasting->forecastStock($history)]);
    }
}
