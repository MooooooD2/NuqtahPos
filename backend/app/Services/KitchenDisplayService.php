<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\KitchenOrder;
use App\Models\KitchenOrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Kitchen Display System service
 * Creates KDS orders from POS invoices and manages their lifecycle.
 */
class KitchenDisplayService
{
    /**
     * Create a kitchen order from a POS invoice.
     */
    public function createFromInvoice(Invoice $invoice, array $options = []): KitchenOrder
    {
        return DB::transaction(function () use ($invoice, $options) {
            $order = KitchenOrder::create([
                'invoice_id' => $invoice->id,
                'branch_id' => $invoice->branch_id,
                'order_number' => $this->generateOrderNumber(),
                'table_number' => $options['table_number'] ?? null,
                'order_type' => $options['order_type'] ?? 'dine_in',
                'status' => 'pending',
                'notes' => $options['notes'] ?? $invoice->notes,
            ]);

            foreach ($invoice->items as $item) {
                KitchenOrderItem::create([
                    'kitchen_order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name ?? $item->product?->name ?? 'Item',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ?? null,
                    'notes' => $item->notes ?? null,
                    'status' => 'pending',
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * Create a manual kitchen order (walk-in, table order).
     */
    public function createManual(array $data): KitchenOrder
    {
        return DB::transaction(function () use ($data) {
            $order = KitchenOrder::create([
                'branch_id' => $data['branch_id'] ?? null,
                'order_number' => $this->generateOrderNumber(),
                'table_number' => $data['table_number'] ?? null,
                'order_type' => $data['order_type'] ?? 'dine_in',
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                KitchenOrderItem::create([
                    'kitchen_order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * Accept/start preparing an order.
     */
    public function accept(int $orderId): KitchenOrder
    {
        $order = KitchenOrder::findOrFail($orderId);
        $order->update([
            'status' => 'preparing',
            'accepted_at' => now(),
        ]);
        $order->items()->update(['status' => 'preparing']);

        return $order;
    }

    /**
     * Mark order as ready.
     */
    public function markReady(int $orderId): KitchenOrder
    {
        $order = KitchenOrder::findOrFail($orderId);
        $order->update([
            'status' => 'ready',
            'ready_at' => now(),
        ]);
        $order->items()->whereNotIn('status', ['cancelled'])->update(['status' => 'done']);

        return $order;
    }

    /**
     * Mark order as served.
     */
    public function markServed(int $orderId): KitchenOrder
    {
        $order = KitchenOrder::findOrFail($orderId);
        $order->update([
            'status' => 'served',
            'served_at' => now(),
        ]);

        return $order;
    }

    /**
     * Cancel order.
     */
    public function cancel(int $orderId): KitchenOrder
    {
        $order = KitchenOrder::findOrFail($orderId);
        $order->update(['status' => 'cancelled']);
        $order->items()->update(['status' => 'cancelled']);

        return $order;
    }

    /**
     * Update single item status.
     */
    public function updateItemStatus(int $itemId, string $status): KitchenOrderItem
    {
        $item = KitchenOrderItem::findOrFail($itemId);
        $item->update(['status' => $status]);

        // Auto-update parent order if all items are done
        $order = $item->kitchenOrder;
        $allDone = $order->items()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->doesntExist();

        if ($allDone && $order->status === 'preparing') {
            $order->update(['status' => 'ready', 'ready_at' => now()]);
        }

        return $item;
    }

    /**
     * Get active orders for KDS display.
     */
    public function getActiveOrders(?int $branchId = null, ?string $status = null): \Illuminate\Database\Eloquent\Collection
    {
        return KitchenOrder::with('items')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($status !== null, fn ($q) => $q->where('status', $status), fn ($q) => $q->active())
            ->orderBy('created_at')
            ->get()
            ->map(function ($order) {
                $order->elapsed_minutes_val = $order->elapsed_minutes;
                $order->is_urgent_val = $order->is_urgent;

                return $order;
            });
    }

    /**
     * KDS statistics for the current shift.
     */
    public function getStats(?int $branchId = null): array
    {
        $base = KitchenOrder::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('created_at', today());

        $completed = (clone $base)->where('status', 'served');

        $avgMinutes = $completed->whereNotNull('ready_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, ready_at)) as avg_min')
            ->value('avg_min');

        return [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'preparing' => (clone $base)->where('status', 'preparing')->count(),
            'ready' => (clone $base)->where('status', 'ready')->count(),
            'served_today' => $completed->count(),
            'avg_prep_min' => $avgMinutes ? round($avgMinutes, 1) : 0,
        ];
    }

    private function generateOrderNumber(): string
    {
        $last = KitchenOrder::whereDate('created_at', today())->max('id') ?? 0;

        return 'K' . date('md') . str_pad($last + 1, 3, '0', STR_PAD_LEFT);
    }
}
