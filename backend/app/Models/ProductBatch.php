<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property string $batch_number
 * @property string|null $lot_number
 * @property Carbon|null $manufacture_date
 * @property Carbon|null $expiry_date
 * @property int $original_qty
 * @property int $remaining_qty
 * @property string|null $cost_price
 * @property int|null $supplier_id
 * @property string|null $notes
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProductBatch extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'batch_number', 'lot_number',
        'manufacture_date', 'expiry_date', 'original_qty', 'remaining_qty',
        'cost_price', 'supplier_id', 'notes', 'status',
    ];

    protected $casts = [
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'cost_price' => 'decimal:4',
        'original_qty' => 'integer',
        'remaining_qty' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isExpired(): bool
    {
        /** @var Carbon|null $expiry */
        $expiry = $this->expiry_date;

        return $expiry !== null && $expiry->isPast();
    }

    /** @param Builder $query */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }

    /** @param Builder $query */
    public function scopeFefo($query)
    {
        return $query->active()->orderByRaw('expiry_date IS NULL, expiry_date ASC');
    }
}
