<?php

namespace App\Services;

use App\Models\Promotion;

class PromotionService
{
    /**
     * Find all valid promotions applicable to the given cart.
     *
     * @param array $items [['product_id', 'quantity', 'subtotal', 'product' => Product], ...]
     * @param float $orderTotal cart total before promotion
     * @return array ['discount' => float, 'applied' => [['name', 'discount'], ...], 'free_items' => [...]]
     */
    public function apply(array $items, float $orderTotal): array
    {
        $promotions = Promotion::where('is_active', true)
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $today);
            })
            ->where(function ($q) {
                $today = now()->toDateString();
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $today);
            })
            ->limit(200)
            ->get();

        $totalDiscount = 0.0;
        $applied = [];
        $freeItems = [];

        foreach ($promotions as $promo) {
            if ($promo->min_order_amount > 0 && $orderTotal < $promo->min_order_amount) {
                continue;
            }

            // Filter applicable items
            $promoItems = collect($items)->filter(function ($item) use ($promo) {
                if ($promo->product_id && $item['product_id'] != $promo->product_id) {
                    return false;
                }
                if ($promo->product_category && ($item['product']->category ?? '') !== $promo->product_category) {
                    return false;
                }

                return true;
            });

            if ($promoItems->isEmpty()) {
                continue;
            }

            switch ($promo->type) {
                case 'percentage':
                    $promoSubtotal = $promoItems->sum('subtotal');
                    $discount = round($promoSubtotal * ($promo->value / 100), 2);
                    $totalDiscount += $discount;
                    $applied[] = ['name' => $promo->name, 'type' => 'percentage', 'discount' => $discount];
                    break;

                case 'fixed':
                    $discount = round((float) $promo->value, 2);
                    $totalDiscount += $discount;
                    $applied[] = ['name' => $promo->name, 'type' => 'fixed', 'discount' => $discount];
                    break;

                case 'buy_x_get_y':
                    if ($promo->buy_qty <= 0 || $promo->get_qty <= 0) {
                        break;
                    }
                    foreach ($promoItems as $item) {
                        $sets = (int) floor($item['quantity'] / ($promo->buy_qty + $promo->get_qty));
                        $freeQty = $sets * $promo->get_qty;
                        if ($freeQty <= 0) {
                            continue;
                        }
                        $unitPrice = $item['subtotal'] / $item['quantity'];
                        $discount = round($unitPrice * $freeQty, 2);
                        $totalDiscount += $discount;
                        $freeItems[] = [
                            'product_id' => $item['product_id'],
                            'product_name' => $item['product']->name ?? '',
                            'quantity' => $freeQty,
                            'promotion' => $promo->name,
                        ];
                        $applied[] = ['name' => $promo->name, 'type' => 'buy_x_get_y', 'discount' => $discount];
                    }
                    break;
            }
        }

        // Cap stacked promotions at the configurable max-discount percentage.
        // Without this cap, simultaneous promotions could grant a 100% discount.
        $maxPromoPercent = (float) config('promotions.max_discount_percent', 50);
        $maxAllowed = $orderTotal * ($maxPromoPercent / 100);

        return [
            'discount' => min($totalDiscount, $maxAllowed, $orderTotal),
            'applied' => $applied,
            'free_items' => $freeItems,
        ];
    }
}
