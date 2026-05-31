<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async job dispatched by StockAlertCommand (and by StockService after every
 * deduction that pushes quantity to or below min_stock).
 *
 * Supports four alert types:
 *   low_stock       — quantity ≤ min_stock (and > 0)
 *   out_of_stock    — quantity = 0
 *   expiry_critical — a batch expires within 7 days
 *   expiry_warning  — a batch expires within 30 days
 *
 * Extend $extra with notification payloads (email, WhatsApp, push) as needed.
 */
class ProcessStockAlert implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string $alertType low_stock | out_of_stock | expiry_critical | expiry_warning
     * @param array $extra Optional context (batch_id, expiry_date, …)
     */
    public function __construct(
        private int $productId,
        private int $currentQty,
        private string $alertType = 'low_stock',
        private array $extra = [],
    ) {}

    public function handle(): void
    {
        $product = Product::find($this->productId);
        if (! $product) {
            return;
        }

        $context = array_merge([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $this->currentQty,
            'min_stock' => $product->min_stock,
            'alert_type' => $this->alertType,
            'timestamp' => now()->toIso8601String(),
        ], $this->extra);

        match ($this->alertType) {
            'out_of_stock' => Log::channel('audit')->error('stock.out_of_stock', $context),
            'expiry_critical' => Log::channel('audit')->error('stock.expiry_critical', $context),
            'expiry_warning' => Log::channel('audit')->warning('stock.expiry_warning', $context),
            default => Log::channel('audit')->warning('stock.low_stock', $context),
        };

        // Extension point: dispatch WhatsApp/email notifications here
        // Example:
        // if (config('whatsapp.enabled')) {
        //     \App\Jobs\SendWhatsAppAlert::dispatch($product, $this->alertType, $context);
        // }
    }
}
