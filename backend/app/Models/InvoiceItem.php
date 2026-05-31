<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $product_id
 * @property string $product_name
 * @property int $quantity
 * @property int $returned_qty
 * @property string $price
 * @property string $cost_price
 * @property string $subtotal
 * @property string $tax_rate
 * @property string $tax_amount
 * @property string $returned_tax
 * @property int|null $warehouse_id
 * @property int|null $batch_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'product_id', 'product_name', 'quantity', 'returned_qty', 'price', 'cost_price', 'subtotal', 'tax_rate', 'tax_amount', 'returned_tax', 'warehouse_id', 'batch_id'];

    protected $casts = [
        'quantity' => 'integer',
        'returned_qty' => 'integer',
        'price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'returned_tax' => 'decimal:4',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

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
        return $this->belongsTo(ProductBatch::class);
    }
}
