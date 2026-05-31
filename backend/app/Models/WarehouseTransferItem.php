<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseTransferItem extends Model
{
    protected $fillable = ['transfer_id', 'product_id', 'batch_id', 'quantity'];

    public function transfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
