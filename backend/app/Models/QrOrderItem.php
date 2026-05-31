<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrOrderItem extends Model
{
    protected $fillable = [
        'qr_order_id', 'product_id', 'product_name', 'price', 'quantity', 'notes',
    ];

    public function qrOrder(): BelongsTo
    {
        return $this->belongsTo(QrOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
