<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashbackRule extends Model
{
    protected $fillable = ['name', 'percentage', 'min_purchase', 'max_cashback', 'is_active'];

    protected $casts = [
        'percentage' => 'float',
        'min_purchase' => 'float',
        'max_cashback' => 'float',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Calculate cashback amount for a given purchase total.
     */
    public function calculate(float $purchaseTotal): float
    {
        if ($purchaseTotal < $this->min_purchase) {
            return 0;
        }

        $cashback = ($purchaseTotal * $this->percentage) / 100;

        if ($this->max_cashback !== null) {
            $cashback = min($cashback, $this->max_cashback);
        }

        return round($cashback, 2);
    }
}
