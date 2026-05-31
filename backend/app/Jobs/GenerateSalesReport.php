<?php

namespace App\Jobs;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * #27 تقارير المبيعات الثقيلة تُعالَج في الخلفية عبر Queue
 */
class GenerateSalesReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        private string $startDate,
        private string $endDate,
        private int $userId,
        private string $cacheKey,
    ) {}

    public function handle(): void
    {
        $end = $this->endDate . ' 23:59:59';

        $totals = Invoice::where('status', 'completed')
            ->whereBetween('created_at', [$this->startDate, $end])
            ->selectRaw('COUNT(*) as total_count, SUM(final_total) as total_revenue, SUM(tax_amount) as total_tax, SUM(discount) as total_discount')
            ->first();

        $byPayment = Invoice::where('status', 'completed')
            ->whereBetween('created_at', [$this->startDate, $end])
            ->selectRaw('payment_method, COUNT(*) as count, SUM(final_total) as total')
            ->groupBy('payment_method')
            ->get();

        $topProducts = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->where('invoices.status', 'completed')
            ->whereBetween('invoices.created_at', [$this->startDate, $end])
            ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_sales')
            ->groupBy('product_name')
            ->orderByDesc('total_sales')
            ->limit(20)
            ->get();

        $result = [
            'status' => 'ready',
            'generated_at' => now()->toDateTimeString(),
            'period' => ['from' => $this->startDate, 'to' => $this->endDate],
            'total_revenue' => $totals->total_revenue ?? 0,
            'total_tax' => $totals->total_tax ?? 0,
            'total_discount' => $totals->total_discount ?? 0,
            'total_count' => $totals->total_count ?? 0,
            'by_payment' => $byPayment,
            'top_products' => $topProducts,
        ];

        // نخزّن في Cache لمدة ساعة — المستخدم يسحبها بـ cacheKey
        Cache::put($this->cacheKey, $result, 3600);
    }

    public function failed(Throwable $e): void
    {
        Cache::put($this->cacheKey, ['status' => 'failed', 'error' => $e->getMessage()], 600);
    }
}
