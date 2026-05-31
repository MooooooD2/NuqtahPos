<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitConversion extends Model
{
    protected $fillable = ['product_id', 'purchase_unit_id', 'sale_unit_id', 'conversion_factor'];

    protected $casts = ['conversion_factor' => 'decimal:6'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseUnit()
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function saleUnit()
    {
        return $this->belongsTo(Unit::class, 'sale_unit_id');
    }

    /**
     * تحويل كمية شراء إلى كمية بيع
     */
    public function toPurchaseQty(float $saleQty): float
    {
        return $this->conversion_factor > 0 ? $saleQty / $this->conversion_factor : $saleQty;
    }

    public function toSaleQty(float $purchaseQty): float
    {
        return $purchaseQty * $this->conversion_factor;
    }
}
