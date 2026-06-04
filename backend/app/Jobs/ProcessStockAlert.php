<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStockAlert implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param string $alertType low_stock | out_of_stock | expiry_critical | expiry_warning
     * @param array  $extra     Optional context (batch_id, expiry_date, …)
     */
    public function __construct(
        private int    $productId,
        private int    $currentQty,
        private string $alertType = 'low_stock',
        private array  $extra     = [],
    ) {}

    public function handle(NotificationService $notifier): void
    {
        $product = Product::find($this->productId);
        if (! $product) {
            return;
        }

        $context = array_merge([
            'product_id'   => $product->id,
            'product_name' => $product->name,
            'quantity'     => $this->currentQty,
            'min_stock'    => $product->min_stock,
            'alert_type'   => $this->alertType,
            'timestamp'    => now()->toIso8601String(),
        ], $this->extra);

        match ($this->alertType) {
            'out_of_stock'    => Log::channel('audit')->error('stock.out_of_stock', $context),
            'expiry_critical' => Log::channel('audit')->error('stock.expiry_critical', $context),
            'expiry_warning'  => Log::channel('audit')->warning('stock.expiry_warning', $context),
            default           => Log::channel('audit')->warning('stock.low_stock', $context),
        };

        // Store in-app notification for admin users (direct DB write, no queue)
        if (in_array($this->alertType, ['low_stock', 'out_of_stock'], true)) {
            $notifier->lowStock(
                $product->id,
                $product->name,
                $this->currentQty,
                (int) ($product->min_stock ?? 0),
            );
        }
    }
}
