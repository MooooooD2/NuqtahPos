<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string|null $barcode
 * @property string|null $category
 * @property string|null $image
 * @property string|null $description
 * @property bool $is_active
 * @property string|null $supplier
 * @property string $price
 * @property string|null $wholesale_price
 * @property string|null $vip_price
 * @property string $cost_price
 * @property string|null $avg_cost
 * @property string|null $last_cost
 * @property int $quantity
 * @property int $min_stock
 * @property int|null $reorder_point
 * @property int|null $reorder_qty
 * @property bool $track_batches
 * @property int|null $unit_id
 * @property int|null $tax_category_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool        $low_stock
 */
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    // #7 fillable صريح — quantity محذوف (يتعدل عبر StockService فقط)
    protected $fillable = ['name', 'price', 'wholesale_price', 'vip_price', 'cost_price', 'avg_cost', 'last_cost', 'min_stock', 'reorder_point', 'reorder_qty', 'track_batches', 'barcode', 'category', 'image', 'description', 'is_active', 'supplier', 'unit_id', 'tax_category_id'];

    protected $hidden = ['deleted_at'];

    protected $casts = ['price' => 'decimal:4', 'wholesale_price' => 'decimal:4', 'vip_price' => 'decimal:4', 'cost_price' => 'decimal:4', 'avg_cost' => 'decimal:4', 'last_cost' => 'decimal:4', 'min_stock' => 'integer', 'quantity' => 'integer', 'track_batches' => 'boolean', 'is_active' => 'boolean'];

    /** Return the effective selling price for the given customer price level. */
    public function priceFor(string $level): string
    {
        return match ($level) {
            'wholesale' => $this->wholesale_price > 0 ? $this->wholesale_price : $this->price,
            'vip' => $this->vip_price > 0 ? $this->vip_price : $this->price,
            default => $this->price,
        };
    }

    public function getLowStockAttribute(): bool
    {
        return $this->quantity <= $this->min_stock;
    }

    /**
     * Scope: products whose current quantity is at or below their minimum stock threshold.
     */
    public function scopeLowStock(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_stock');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxCategory()
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function warehouseStock()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function recipe()
    {
        return $this->hasMany(ProductRecipe::class);
    }

    public function usedInRecipes()
    {
        return $this->hasMany(ProductRecipe::class, 'ingredient_id');
    }

    public function unitConversion()
    {
        return $this->hasOne(UnitConversion::class);
    }
}
