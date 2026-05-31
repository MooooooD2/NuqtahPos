<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $return_id
 * @property int $product_id
 * @property string $product_name
 * @property int $quantity
 * @property string $price
 * @property string $subtotal
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReturnItem extends Model
{
    protected $fillable = ['return_id', 'product_id', 'product_name', 'quantity', 'price', 'subtotal'];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'return_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
