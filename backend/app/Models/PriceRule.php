<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dynamic Pricing Rule — Phase 9
 *
 * Supports: Happy Hour, Bulk Discounts, Day-of-week, Category, Loyalty Tier, Flat Price overrides.
 *
 * @property int $id
 * @property string $name
 * @property string $rule_type
 * @property string $discount_type
 * @property float $discount_value
 * @property array|null $product_ids
 * @property array|null $category_ids
 * @property string|null $time_start
 * @property string|null $time_end
 * @property array|null $days_of_week
 * @property string|null $valid_from
 * @property string|null $valid_until
 * @property float|null $min_quantity
 * @property int $priority
 * @property bool $is_active
 * @property bool $stackable
 */
class PriceRule extends Model
{
    protected $fillable = [
        'name', 'description', 'rule_type', 'discount_type', 'discount_value',
        'product_ids', 'category_ids', 'customer_group_id',
        'time_start', 'time_end', 'days_of_week',
        'valid_from', 'valid_until', 'min_quantity',
        'priority', 'is_active', 'stackable',
    ];

    protected $casts = [
        'product_ids' => 'array',
        'category_ids' => 'array',
        'days_of_week' => 'array',
        'discount_value' => 'float',
        'min_quantity' => 'float',
        'is_active' => 'boolean',
        'stackable' => 'boolean',
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this rule is currently active based on time/date constraints.
     */
    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();
        $date = $now->toDateString();
        $time = $now->format('H:i:s');
        $dow = $now->dayOfWeekIso; // 1=Mon…7=Sun

        // Date range check
        if ($this->valid_from && $date < $this->valid_from->toDateString()) {
            return false;
        }
        if ($this->valid_until && $date > $this->valid_until->toDateString()) {
            return false;
        }

        // Day of week check
        if ($this->days_of_week && ! in_array($dow, $this->days_of_week)) {
            return false;
        }

        // Time range check (happy hour)
        if ($this->time_start && $this->time_end) {
            if ($time < $this->time_start || $time > $this->time_end) {
                return false;
            }
        }

        return true;
    }

    /**
     * Applies this rule to a given price and quantity.
     *
     * @return float The adjusted (discounted) price per unit
     */
    public function applyToPrice(float $originalPrice, float $quantity = 1): float
    {
        if (! $this->isCurrentlyActive()) {
            return $originalPrice;
        }

        if ($this->min_quantity !== null && $quantity < $this->min_quantity) {
            return $originalPrice;
        }

        return match ($this->discount_type) {
            'percentage' => round($originalPrice * (1 - $this->discount_value / 100), 4),
            'fixed_amount' => max(0, round($originalPrice - $this->discount_value, 4)),
            'new_price' => max(0, $this->discount_value),
            default => $originalPrice,
        };
    }

    /**
     * Check if this rule applies to a given product.
     */
    public function appliesToProduct(int $productId, ?string $category = null): bool
    {
        // No scope restriction = applies to all
        if (empty($this->product_ids) && empty($this->category_ids)) {
            return true;
        }

        if (! empty($this->product_ids) && in_array($productId, $this->product_ids)) {
            return true;
        }

        if ($category && ! empty($this->category_ids) && in_array($category, $this->category_ids)) {
            return true;
        }

        return false;
    }
}
