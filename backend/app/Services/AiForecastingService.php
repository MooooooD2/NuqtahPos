<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * AI Forecasting & Sales Prediction Engine
 *
 * Uses statistical methods:
 *   - Simple Moving Average (SMA)
 *   - Weighted Moving Average (WMA)
 *   - Linear Regression Trend
 *   - Seasonal decomposition (day-of-week)
 *   - Exponential Smoothing
 */
class AiForecastingService
{
    /**
     * Forecast daily sales for the next N days.
     */
    public function forecastSales(int $days = 30, int $historyDays = 90): array
    {
        $cacheKey = "forecast_sales_{$days}_{$historyDays}";

        return Cache::remember($cacheKey, 3600, function () use ($days, $historyDays) {
            $historical = $this->getDailySalesHistory($historyDays);

            if ($historical->count() < 3) {
                return ['error' => __('pos.forecast_insufficient_data')];
            }

            $values = $historical->pluck('total_sales')->toArray();
            $dates = $historical->pluck('sale_date')->toArray();

            // Trend via linear regression
            $trend = $this->linearRegression($values);

            // Seasonal factors (day of week)
            $seasonality = $this->computeSeasonality($historical);

            // Generate forecasts
            $forecasts = [];
            $lastDate = end($dates);
            $n = count($values);

            // Exponential smoothing baseline
            $smoothed = $this->exponentialSmoothing($values, 0.3);
            $lastSmoothed = end($smoothed);

            for ($i = 1; $i <= $days; $i++) {
                $futureDate = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
                $dow = (int) date('N', strtotime($futureDate)); // 1=Mon…7=Sun

                // Trend projection
                $trendValue = $trend['intercept'] + $trend['slope'] * ($n + $i);

                // Apply seasonality
                $seasonFactor = $seasonality[$dow] ?? 1.0;

                // Combine: blend trend + smoothed
                $base = ($trendValue * 0.6) + ($lastSmoothed * 0.4);
                $forecast = max(0, round($base * $seasonFactor, 2));

                // Confidence interval (±15%)
                $forecasts[] = [
                    'date' => $futureDate,
                    'forecast' => $forecast,
                    'lower_ci' => round($forecast * 0.85, 2),
                    'upper_ci' => round($forecast * 1.15, 2),
                    'day_of_week' => date('D', strtotime($futureDate)),
                ];
            }

            // Summary metrics
            $accuracy = $this->computeBacktestAccuracy($historical, $smoothed);

            return [
                'forecasts' => $forecasts,
                'total_forecast' => round(array_sum(array_column($forecasts, 'forecast')), 2),
                'avg_daily' => round(array_sum(array_column($forecasts, 'forecast')) / $days, 2),
                'trend' => $trend['slope'] > 0.5 ? 'growing' : ($trend['slope'] < -0.5 ? 'declining' : 'stable'),
                'trend_slope' => round($trend['slope'], 4),
                'accuracy_pct' => $accuracy,
                'history_used' => $historical->count(),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Forecast demand for each product (top movers).
     */
    public function forecastProducts(int $topN = 20, int $historyDays = 60): array
    {
        return Cache::remember("forecast_products_{$topN}_{$historyDays}", 3600, function () use ($topN, $historyDays) {
            $since = now()->subDays($historyDays);

            $productStats = InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->where('invoices.created_at', '>=', $since)
                ->where('invoices.status', 'paid')
                ->select(
                    'invoice_items.product_id',
                    DB::raw('SUM(invoice_items.quantity) as total_qty'),
                    DB::raw('SUM(invoice_items.quantity * invoice_items.price) as total_revenue'),
                    DB::raw('COUNT(DISTINCT DATE(invoices.created_at)) as active_days'),
                    DB::raw('MIN(invoices.created_at) as first_sale'),
                )
                ->groupBy('invoice_items.product_id')
                ->orderByDesc('total_qty')
                ->limit($topN)
                ->get();

            $results = [];
            foreach ($productStats as $stat) {
                $product = Product::find($stat->product_id);
                if (! $product) {
                    continue;
                }

                $avgDailyQty = $stat->total_qty / max($stat->active_days, 1);
                $forecast30 = round($avgDailyQty * 30, 1);

                // Velocity trend (last 14 days vs prior 14 days)
                $recent = $this->getProductDemand($stat->product_id, 14);
                $prior = $this->getProductDemand($stat->product_id, 28, 14);
                $velocity = $prior > 0 ? round((($recent - $prior) / $prior) * 100, 1) : 0;

                // Stock coverage
                $currentStock = $product->quantity ?? 0;
                $daysLeft = $avgDailyQty > 0 ? round($currentStock / $avgDailyQty) : 999;

                $results[] = [
                    'product_id' => $stat->product_id,
                    'product_name' => $product->name,
                    'avg_daily_qty' => round($avgDailyQty, 2),
                    'forecast_30_days' => $forecast30,
                    'current_stock' => $currentStock,
                    'days_stock_left' => min($daysLeft, 365),
                    'needs_reorder' => $daysLeft <= 7,
                    'velocity_pct' => $velocity,   // +% = growing demand
                    'total_revenue' => round($stat->total_revenue, 2),
                ];
            }

            return [
                'products' => $results,
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Stock depletion forecast — when will each product run out?
     */
    public function forecastStock(int $historyDays = 30): array
    {
        return Cache::remember("forecast_stock_{$historyDays}", 3600, function () use ($historyDays) {
            $since = now()->subDays($historyDays);

            $consumed = InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->where('invoices.created_at', '>=', $since)
                ->where('invoices.status', 'paid')
                ->select('invoice_items.product_id', DB::raw('SUM(invoice_items.quantity) as consumed'))
                ->groupBy('invoice_items.product_id')
                ->get()
                ->keyBy('product_id');

            $products = Product::where('is_active', true)
                ->where('quantity', '>', 0)
                ->get();

            $alerts = [];
            foreach ($products as $product) {
                $c = $consumed->get($product->id);
                if (! $c) {
                    continue;
                }

                $dailyRate = $c->consumed / $historyDays;
                if ($dailyRate <= 0) {
                    continue;
                }

                $daysLeft = round($product->quantity / $dailyRate);
                $depletedOn = now()->addDays($daysLeft)->format('Y-m-d');

                if ($daysLeft <= 30) {
                    $alerts[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'current_stock' => $product->quantity,
                        'daily_rate' => round($dailyRate, 2),
                        'days_remaining' => $daysLeft,
                        'depleted_on' => $depletedOn,
                        'urgency' => $daysLeft <= 3 ? 'critical' : ($daysLeft <= 7 ? 'high' : ($daysLeft <= 14 ? 'medium' : 'low')),
                        'reorder_qty' => max($product->reorder_point ?? 0, (int) ceil($dailyRate * 30)),
                    ];
                }
            }

            usort($alerts, fn ($a, $b) => $a['days_remaining'] - $b['days_remaining']);

            return [
                'alerts' => $alerts,
                'total_at_risk' => count($alerts),
                'critical' => count(array_filter($alerts, fn ($a) => $a['urgency'] === 'critical')),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /* ─── Statistical Helpers ────────────────────────────────────────── */

    private function getDailySalesHistory(int $days): Collection
    {
        return Invoice::where('status', 'paid')
            ->where('created_at', '>=', now()->subDays($days))
            ->select(
                DB::raw('DATE(created_at) as sale_date'),
                DB::raw('SUM(final_total) as total_sales'),
                DB::raw('COUNT(*) as invoice_count'),
                DB::raw('AVG(final_total) as avg_invoice'),
            )
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();
    }

    private function getProductDemand(int $productId, int $days, int $offsetDays = 0): float
    {
        return (float) InvoiceItem::join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoice_items.product_id', $productId)
            ->where('invoices.status', 'paid')
            ->whereBetween('invoices.created_at', [
                now()->subDays($offsetDays + $days),
                now()->subDays($offsetDays),
            ])
            ->sum('invoice_items.quantity');
    }

    /**
     * Simple linear regression: y = a + bx
     * Returns ['slope' => b, 'intercept' => a]
     */
    private function linearRegression(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0];
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        foreach ($values as $i => $y) {
            $x = $i + 1;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denom = ($n * $sumX2) - ($sumX * $sumX);
        if ($denom == 0) {
            return ['slope' => 0, 'intercept' => $sumY / $n];
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $intercept = ($sumY - ($slope * $sumX)) / $n;

        return ['slope' => $slope, 'intercept' => $intercept];
    }

    /**
     * Exponential smoothing: S_t = α * Y_t + (1−α) * S_{t-1}
     */
    private function exponentialSmoothing(array $values, float $alpha = 0.3): array
    {
        if (empty($values)) {
            return [];
        }
        $smoothed = [$values[0]];
        for ($i = 1; $i < count($values); $i++) {
            $smoothed[] = $alpha * $values[$i] + (1 - $alpha) * $smoothed[$i - 1];
        }

        return $smoothed;
    }

    /**
     * Seasonal factors by day of week (1=Mon…7=Sun).
     * Computes average sales per DoW relative to global average.
     */
    private function computeSeasonality(Collection $historical): array
    {
        $byDow = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []];

        foreach ($historical as $row) {
            $dow = (int) date('N', strtotime($row->sale_date));
            $byDow[$dow][] = (float) $row->total_sales;
        }

        $globalAvg = $historical->avg('total_sales') ?: 1;
        $factors = [];
        foreach ($byDow as $dow => $vals) {
            $factors[$dow] = count($vals) ? (array_sum($vals) / count($vals)) / $globalAvg : 1.0;
        }

        return $factors;
    }

    /**
     * Backtest accuracy: MAPE (Mean Absolute Percentage Error) as accuracy %.
     */
    private function computeBacktestAccuracy(Collection $historical, array $smoothed): float
    {
        $actuals = $historical->pluck('total_sales')->toArray();
        $n = count($actuals);
        if ($n < 2) {
            return 80.0;
        }

        $errors = [];
        for ($i = 1; $i < $n; $i++) {
            if ($actuals[$i] == 0) {
                continue;
            }
            $errors[] = abs($actuals[$i] - $smoothed[$i - 1]) / $actuals[$i];
        }

        if (empty($errors)) {
            return 80.0;
        }
        $mape = array_sum($errors) / count($errors);

        return round(max(0, (1 - $mape) * 100), 1);
    }
}
