<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'po_id', 'product_id', 'product_name',
        'quantity', 'cost_price', 'selling_price',
        'subtotal', 'tax_rate', 'tax_amount',
        'received_quantity', 'rejected_qty', 'quality_status',
        'discrepancy', 'discrepancy_notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'received_quantity' => 'integer',
        'rejected_qty' => 'integer',
        'discrepancy' => 'integer',
        'cost_price' => 'float',
        'selling_price' => 'float',
        'subtotal' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // كمية لم تُستلم بعد
    public function getPendingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->received_quantity);
    }
}
