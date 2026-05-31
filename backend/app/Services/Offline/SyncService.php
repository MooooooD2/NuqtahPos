<?php

namespace App\Services\Offline;

use App\Models\Invoice;
use App\Models\Product;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncService
{
    private const MAX_PRICE_DEVIATION = 0.10; // allow up to 10% price deviation for rounding

    private const MAX_QTY_PER_ITEM = 9999;

    public function __construct(private InvoiceService $invoiceService) {}

    public function syncInvoices(array $invoices): array
    {
        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];

        foreach ($invoices as $inv) {
            $uuid = $inv['offline_uuid'] ?? null;

            if (! $uuid) {
                $results[] = ['offline_uuid' => null, 'status' => 'failed', 'message' => 'Missing offline_uuid'];
                $failed++;

                continue;
            }

            // Idempotency: already synced?
            $existing = Invoice::where('offline_uuid', $uuid)->first();
            if ($existing) {
                $results[] = ['offline_uuid' => $uuid, 'server_id' => $existing->id, 'status' => 'already_synced'];
                $skipped++;

                continue;
            }

            // Fraud detection: validate item prices against current product prices
            $fraudWarnings = $this->detectFraud($inv);
            if (! empty($fraudWarnings)) {
                Log::channel('audit')->warning('offline.sync_fraud_detected', [
                    'offline_uuid' => $uuid,
                    'user_id' => Auth::id(),
                    'warnings' => $fraudWarnings,
                    'ip' => request()->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);
                $results[] = ['offline_uuid' => $uuid, 'status' => 'failed', 'message' => 'Price validation failed: ' . implode('; ', $fraudWarnings)];
                $failed++;

                continue;
            }

            try {
                $invoice = $this->invoiceService->createInvoice($inv);

                Log::channel('audit')->info('offline.invoice_synced', [
                    'offline_uuid' => $uuid,
                    'server_id' => $invoice->id,
                    'user_id' => Auth::id(),
                    'ip' => request()->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                $results[] = ['offline_uuid' => $uuid, 'server_id' => $invoice->id, 'status' => 'synced'];
                $synced++;
            } catch (Throwable $e) {
                Log::warning('offline.sync_failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
                $results[] = ['offline_uuid' => $uuid, 'status' => 'failed', 'message' => $e->getMessage()];
                $failed++;
            }
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    private function detectFraud(array $inv): array
    {
        $warnings = [];
        $items = $inv['items'] ?? [];

        // Cache product prices to avoid N+1 per item
        $productIds = array_column($items, 'product_id');
        $products = Product::whereIn('id', $productIds)
            ->select('id', 'name', 'price')
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $pid = $item['product_id'] ?? null;
            $sentPrice = isset($item['price']) ? (float) $item['price'] : null;
            $qty = (int) ($item['quantity'] ?? 0);

            // Quantity sanity check
            if ($qty > self::MAX_QTY_PER_ITEM) {
                $warnings[] = "Product #{$pid}: quantity {$qty} exceeds maximum allowed";
            }

            // Price deviation check (only when client sends a price)
            if ($sentPrice !== null && $pid && isset($products[$pid])) {
                $dbPrice = (float) $products[$pid]->price;
                if ($dbPrice > 0) {
                    $deviation = abs($sentPrice - $dbPrice) / $dbPrice;
                    if ($deviation > self::MAX_PRICE_DEVIATION) {
                        $warnings[] = sprintf(
                            'Product #%d "%s": sent price %.2f deviates %.1f%% from current price %.2f',
                            $pid,
                            $products[$pid]->name,
                            $sentPrice,
                            $deviation * 100,
                            $dbPrice,
                        );
                    }
                }
            }
        }

        return $warnings;
    }
}
