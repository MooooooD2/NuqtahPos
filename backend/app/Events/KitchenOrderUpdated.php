<?php

namespace App\Events;

use App\Models\KitchenOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast kitchen order status changes to KDS screens in real-time.
 * Requires Laravel Echo + Pusher/Reverb to be configured.
 */
class KitchenOrderUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly KitchenOrder $order,
        public readonly string $action = 'updated',   // created, updated, cancelled
    ) {}

    public function broadcastOn(): Channel
    {
        // Broadcast on branch-specific channel (or global if no branch)
        $channel = $this->order->branch_id
            ? "kitchen.branch.{$this->order->branch_id}"
            : 'kitchen.all';

        return new Channel($channel);
    }

    public function broadcastAs(): string
    {
        return 'KitchenOrderUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'table_number' => $this->order->table_number,
            'order_type' => $this->order->order_type,
            'elapsed_min' => $this->order->elapsed_minutes,
        ];
    }
}
