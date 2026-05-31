<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WasteRecord extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'batch_id',
        'quantity', 'unit_cost', 'total_value',
        'reason', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_cost' => 'float',
        'total_value' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
