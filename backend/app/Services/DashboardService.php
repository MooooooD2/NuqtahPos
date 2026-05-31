<?php

namespace App\Services;

use App\Contracts\Repositories\DashboardRepositoryInterface;
use App\Contracts\Repositories\ProductRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    // Dashboard data TTL: 60 seconds — fresh enough for real-time feel, cheap enough for busy tenants.
    private const DATA_TTL = 60;

    public function __construct(
        private DashboardRepositoryInterface $dashboardRepo,
        private ProductRepositoryInterface $productRepo,
    ) {}

    public function getData(): array
    {
        return Cache::remember('dashboard_data', self::DATA_TTL, function () {
            $today = today()->toDateString();
            $yesterday = today()->subDay()->toDateString();

            $todaySales = $this->dashboardRepo->todaySalesStats($today);
            $yesterdaySales = $this->dashboardRepo->yesterdaySalesTotal($yesterday);

            $todayTotal = $todaySales->total ?? 0;
            $yesterdayTotal = $yesterdaySales->total ?? 0;
            $growth = $yesterdayTotal > 0
                ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 2)
                : 0;

            $productStats = $this->dashboardRepo->productStats();

            return [
                'today_sales_count' => $todaySales->count ?? 0,
                'today_sales_total' => $todayTotal,
                'yesterday_sales_total' => $yesterdayTotal,
                'growth_percentage' => $growth,
                'low_stock_count' => (int) ($productStats->low_stock ?? 0),
                'out_of_stock_count' => (int) ($productStats->out_of_stock ?? 0),
                'total_products' => (int) ($productStats->total ?? 0),
                'total_suppliers' => $this->dashboardRepo->totalSuppliers(),
                'total_revenue' => $this->dashboardRepo->totalRevenue(),
                'recent_invoices' => $this->dashboardRepo->recentInvoices(5),
                'recent_movements' => $this->dashboardRepo->recentMovements(5),
                'top_products' => $this->dashboardRepo->topProducts(
                    today()->startOfMonth()->toDateTimeString(),
                    today()->endOfDay()->toDateTimeString(),
                    5,
                ),
            ];
        });
    }

    public function lowStockAlerts(): array
    {
        $outOfStock = $this->productRepo->outOfStock();
        $lowStock = $this->productRepo->lowStock();

        return [
            'total_alerts' => $outOfStock->count() + $lowStock->count(),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
        ];
    }

    public static function forgetCache(): void
    {
        Cache::forget('dashboard_data');
    }
}
