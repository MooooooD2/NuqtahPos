<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenOrderItem extends Model
{
    protected $fillable = [
        'kitchen_order_id', 'product_id', 'product_name',
        'quantity', 'unit', 'notes', 'status',
    ];

    public function kitchenOrder(): BelongsTo
    {
        return $this->belongsTo(KitchenOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
