<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'name', 'description', 'type', 'value',
        'buy_qty', 'get_qty',
        'product_id', 'product_category',
        'min_order_amount', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'is_active' => 'boolean',
        'buy_qty' => 'integer',
        'get_qty' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->toDateString();
        if ($this->starts_at && $this->starts_at->toDateString() > $today) {
            return false;
        }
        if ($this->ends_at && $this->ends_at->toDateString() < $today) {
            return false;
        }

        return true;
    }
}
