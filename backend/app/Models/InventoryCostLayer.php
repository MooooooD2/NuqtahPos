<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCostLayer extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id',
        'reference_type', 'reference_id',
        'original_qty', 'remaining_qty',
        'unit_cost',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:4',
        'original_qty' => 'integer',
        'remaining_qty' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeWithStock($query)
    {
        return $query->where('remaining_qty', '>', 0);
    }

    // FIFO: oldest layer first
    public function scopeFifo($query)
    {
        return $query->withStock()->orderBy('created_at', 'asc')->orderBy('id', 'asc');
    }

    // LIFO: newest layer first
    public function scopeLifo($query)
    {
        return $query->withStock()->orderBy('created_at', 'desc')->orderBy('id', 'desc');
    }
}
