<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $warehouse_id
 * @property int $product_id
 * @property int $quantity
 * @property int $reserved_qty
 * @property int $min_stock
 * @property-read int $available_qty
 */
class WarehouseStock extends Model
{
    public $timestamps = false;

    protected $table = 'warehouse_stock';

    protected $fillable = ['warehouse_id', 'product_id', 'quantity', 'reserved_qty', 'min_stock'];

    protected $casts = ['quantity' => 'integer', 'reserved_qty' => 'integer', 'min_stock' => 'integer'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function getAvailableQtyAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved_qty);
    }
}
