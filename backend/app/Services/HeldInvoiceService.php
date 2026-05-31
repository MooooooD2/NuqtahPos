<?php

namespace App\Services;

use App\Models\HeldInvoice;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class HeldInvoiceService
{
    public function hold(array $data): HeldInvoice
    {
        $holdNumber = SequenceService::next('held_invoice', 'HLD');

        $items = $data['items'] ?? [];
        $subtotal = collect($items)->sum(fn ($i) => (float) $i['price'] * (int) $i['quantity']);
        $discount = (float) ($data['discount'] ?? 0);
        $total = max(0, $subtotal - $discount);

        return HeldInvoice::create([
            'hold_number' => $holdNumber,
            'cashier_id' => Auth::id(),
            'cashier_name' => Auth::user()?->full_name ?? '',
            'customer_id' => $data['customer_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'cart_data' => [
                'items' => $items,
                'discount' => $discount,
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discount, 2),
            'total' => round($total, 2),
            'notes' => $data['notes'] ?? null,
            'status' => 'held',
            'expires_at' => isset($data['expires_in_minutes'])
                ? now()->addMinutes((int) $data['expires_in_minutes'])
                : null,
        ]);
    }

    public function active(): Collection
    {
        return HeldInvoice::where('status', 'held')
            ->where('cashier_id', Auth::id())
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get(['id', 'hold_number', 'cashier_name', 'customer_name', 'total', 'notes', 'created_at', 'expires_at']);
    }

    public function resume(HeldInvoice $held): HeldInvoice
    {
        if ($held->cashier_id !== Auth::id()) {
            throw new Exception(__('pos.held_invoice_not_yours'));
        }

        if ($held->status !== 'held') {
            throw new Exception(__('pos.held_invoice_not_available'));
        }

        if ($held->expires_at && $held->expires_at->isPast()) {
            $held->update(['status' => 'expired']);

            throw new Exception(__('pos.held_invoice_expired'));
        }

        $held->update(['status' => 'resumed']);

        return $held;
    }

    public function discard(HeldInvoice $held): void
    {
        $user = Auth::user();
        if ($held->cashier_id !== Auth::id() && ! $user?->hasRole('admin')) {
            throw new Exception(__('pos.held_invoice_not_yours'));
        }

        if (! in_array($held->status, ['held', 'expired'])) {
            throw new Exception(__('pos.held_invoice_not_available'));
        }
        $held->update(['status' => 'discarded']);
    }

    public function expireStale(): int
    {
        return HeldInvoice::where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
