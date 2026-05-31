<?php

namespace App\Console\Commands;

use App\Jobs\ProcessStockAlert;
use App\Services\StockAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled command that runs stock-health checks and dispatches alert jobs.
 *
 * FIX: previously queried models directly and used the wrong column name
 *      `movement_type = 'type'` for consumption velocity — now delegates to
 *      StockAlertService which uses the correct `movement_type` column.
 *
 * Run via: php artisan stock:alert
 * Schedule: daily (or more frequently as needed).
 */
class StockAlertCommand extends Command
{
    protected $signature = 'stock:alert
                            {--write-off-expired : Also write off all expired batches}
                            {--lookback=7        : Days of history for reorder velocity calculation}';

    protected $description = 'Check low stock, expiry alerts, and smart reorder suggestions; optionally write off expired batches.';

    public function __construct(private StockAlertService $alertService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->checkLowAndOutOfStock();
        $this->checkExpiryAlerts();
        $this->suggestSmartReorders();

        if ($this->option('write-off-expired')) {
            $this->writeOffExpired();
        }

        return Command::SUCCESS;
    }

    // ── Steps ────────────────────────────────────────────────────────────────

    private function checkLowAndOutOfStock(): void
    {
        $lowStock = $this->alertService->getLowStock();
        $outOfStock = $this->alertService->getOutOfStock();

        foreach ($lowStock as $item) {
            ProcessStockAlert::dispatch($item['product_id'], $item['quantity'], 'low_stock');
        }

        foreach ($outOfStock as $item) {
            ProcessStockAlert::dispatch($item['product_id'], 0, 'out_of_stock');
        }

        $this->info(sprintf(
            'Stock levels: %d low, %d out-of-stock.',
            $lowStock->count(),
            $outOfStock->count(),
        ));
    }

    private function checkExpiryAlerts(): void
    {
        // 7-day critical window
        $critical = $this->alertService->getNearExpiryBatches(7);
        // 30-day warning window (excludes the 7-day critical)
        $warning = $this->alertService->getNearExpiryBatches(30)
            ->where('days_to_expiry', '>', 7);

        foreach ($critical as $item) {
            ProcessStockAlert::dispatch($item['product_id'], $item['remaining_qty'], 'expiry_critical', [
                'batch_id' => $item['batch_id'],
                'expiry_date' => $item['expiry_date'],
            ]);
        }

        // FIX: warning batches were counted but never dispatched — add dispatch loop
        foreach ($warning as $item) {
            ProcessStockAlert::dispatch($item['product_id'], $item['remaining_qty'], 'expiry_warning', [
                'batch_id' => $item['batch_id'],
                'expiry_date' => $item['expiry_date'],
            ]);
        }

        if ($critical->count() > 0) {
            $this->error(sprintf('CRITICAL: %d batch(es) expiring within 7 days.', $critical->count()));
        }
        if ($warning->count() > 0) {
            $this->warn(sprintf('Warning: %d batch(es) expiring within 30 days.', $warning->count()));
        }

        // Log expired-but-not-written-off batches
        $expired = $this->alertService->getExpiredBatches();
        if ($expired->count() > 0) {
            foreach ($expired as $item) {
                Log::channel('audit')->warning('batch.expired_stock_remaining', [
                    'batch_id' => $item['batch_id'],
                    'product_id' => $item['product_id'],
                    'remaining_qty' => $item['remaining_qty'],
                    'expired_days_ago' => $item['expired_days_ago'],
                ]);
            }
            $this->warn(sprintf(
                '%d expired batch(es) still holding stock — run with --write-off-expired to clear them.',
                $expired->count(),
            ));
        }
    }

    private function suggestSmartReorders(): void
    {
        $lookback = max(1, (int) $this->option('lookback'));
        $suggestions = $this->alertService->getReorderSuggestions($lookback);

        foreach ($suggestions as $item) {
            Log::channel('audit')->info('stock.reorder_suggested', [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'reorder_point' => $item['reorder_point'],
                'suggested_order_qty' => $item['suggested_order_qty'],
                'avg_daily_velocity' => $item['avg_daily_velocity'],
                'days_of_stock_left' => $item['days_of_stock_left'],
                'urgency' => $item['urgency'],
                'supplier' => $item['supplier'],
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $urgent = collect($suggestions)->where('urgency', 'urgent')->count();
        $message = sprintf('%d product(s) need reordering', count($suggestions));
        if ($urgent > 0) {
            $message .= sprintf(' (%d urgent)', $urgent);
        }
        $message .= '.';

        $urgent > 0
            ? $this->error($message)
            : $this->info($message);
    }

    private function writeOffExpired(): void
    {
        $results = $this->alertService->writeOffExpiredBatches();

        $this->info(sprintf('Written off %d expired batch(es).', count($results)));

        foreach ($results as $r) {
            $this->line(sprintf(
                '  ✓ Batch %s | %s | qty %d | expired %s',
                $r['batch_number'],
                $r['product_name'],
                $r['quantity'],
                $r['expiry_date'],
            ));
        }
    }
}
