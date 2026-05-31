<?php

namespace App\Services;

use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic Pricing Engine — Phase 9
 *
 * Evaluates all active price rules for a product and returns the best price.
 * Supports: Happy Hour, Bulk, Day-of-Week, Category, Loyalty Tier, Flat Price.
 */
class DynamicPricingService
{
    /**
     * Get the effective price for a product at a given quantity.
     *
     * @return array ['price' => float, 'original_price' => float, 'rule' => PriceRule|null, 'discount_pct' => float]
     */
    public function getEffectivePrice(int $productId, float $quantity = 1, ?int $customerGroupId = null): array
    {
        $product = Product::find($productId);
        if (! $product) {
            return $this->noDiscount(0);
        }

        $originalPrice = (float) $product->price;
        $rules = $this->getActiveRulesForProduct($productId, $product->category, $customerGroupId);

        if ($rules->isEmpty()) {
            return $this->noDiscount($originalPrice);
        }

        // Try each rule in priority order; apply the first that results in a lower price
        // (unless stackable — we allow multiple in that case)
        $bestPrice = $originalPrice;
        $bestRule = null;

        foreach ($rules as $rule) {
            $adjusted = $rule->applyToPrice($originalPrice, $quantity);
            if ($adjusted < $bestPrice) {
                $bestPrice = $adjusted;
                $bestRule = $rule;
                if (! $rule->stackable) {
                    break;
                } // stop at first non-stackable rule
            }
        }

        $discountPct = $originalPrice > 0
            ? round((($originalPrice - $bestPrice) / $originalPrice) * 100, 2)
            : 0;

        return [
            'price' => round($bestPrice, 2),
            'original_price' => $originalPrice,
            'rule' => $bestRule ? [
                'id' => $bestRule->id,
                'name' => $bestRule->name,
                'type' => $bestRule->rule_type,
            ] : null,
            'discount_pct' => $discountPct,
            'has_discount' => $discountPct > 0,
        ];
    }

    /**
     * Evaluate prices for multiple products at once (bulk evaluation for POS).
     */
    public function evaluateBatch(array $items, ?int $customerGroupId = null): array
    {
        $results = [];
        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = (float) ($item['quantity'] ?? 1);
            $results[$productId] = $this->getEffectivePrice($productId, $quantity, $customerGroupId);
        }

        return $results;
    }

    /**
     * Get all currently active rules that apply to a product.
     */
    public function getActiveRulesForProduct(int $productId, ?string $category = null, ?int $customerGroupId = null): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "price_rules_active_{$productId}";

        $rules = Cache::remember($cacheKey, 60, function () {
            return PriceRule::active()
                ->orderByDesc('priority')
                ->get();
        });

        return $rules->filter(function (PriceRule $rule) use ($productId, $category, $customerGroupId) {
            if (! $rule->isCurrentlyActive()) {
                return false;
            }
            if ($rule->customer_group_id && $rule->customer_group_id !== $customerGroupId) {
                return false;
            }

            return $rule->appliesToProduct($productId, $category);
        });
    }

    /**
     * Check if happy hour is currently active.
     */
    public function isHappyHourActive(): bool
    {
        return PriceRule::active()
            ->where('rule_type', 'happy_hour')
            ->get()
            ->contains(fn (PriceRule $r) => $r->isCurrentlyActive());
    }

    /**
     * Get all price rules with their current status (for admin display).
     */
    public function getAllRulesWithStatus(): \Illuminate\Database\Eloquent\Collection
    {
        return PriceRule::orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(function (PriceRule $rule) {
                $rule->is_currently_active = $rule->isCurrentlyActive();

                return $rule;
            });
    }

    private function noDiscount(float $price): array
    {
        return [
            'price' => $price,
            'original_price' => $price,
            'rule' => null,
            'discount_pct' => 0,
            'has_discount' => false,
        ];
    }
}
