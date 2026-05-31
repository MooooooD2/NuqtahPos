<?php

namespace App\Events;

use App\Models\QrOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to POS/KDS screens when a new QR order arrives.
 */
class NewQrOrderPlaced implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly QrOrder $order) {}

    public function broadcastOn(): Channel
    {
        return new Channel('pos.orders');
    }

    public function broadcastAs(): string
    {
        return 'NewQrOrder';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'table_name' => $this->order->qrTable?->table_name,
            'total' => $this->order->total,
            'items_count' => $this->order->items()->count(),
            'customer_name' => $this->order->customer_name,
            'created_at' => $this->order->created_at?->toIso8601String(),
        ];
    }
}
