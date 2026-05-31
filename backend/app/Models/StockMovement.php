<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class StockMovement extends Model
{
    // #19 balance_after و ip_address للتتبع
    protected $fillable = [
        'product_id', 'product_name', 'quantity', 'balance_after',
        'movement_type', 'reference_type', 'reference_id',
        'warehouse_id', 'batch_id',
        'reason', 'employee_id', 'employee_name', 'ip_address',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new RuntimeException('StockMovement records are immutable — updates are not permitted.');
        });

        static::deleting(function () {
            throw new RuntimeException('StockMovement records are immutable — deletes are not permitted.');
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch()
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
