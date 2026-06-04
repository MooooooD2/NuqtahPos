<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    public function __construct(
        private string $productName,
        private int    $currentQty,
        private int    $minStock,
        private int    $productId,
    ) {}

    public function via(mixed $_notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $_notifiable): array
    {
        return [
            'message'      => "Low stock: {$this->productName} ({$this->currentQty} left, min {$this->minStock})",
            'subtitle'     => "Product #{$this->productId}",
            'product_id'   => $this->productId,
            'product_name' => $this->productName,
            'current_qty'  => $this->currentQty,
            'min_stock'    => $this->minStock,
        ];
    }
}
