<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRecipe extends Model
{
    protected $fillable = ['product_id', 'ingredient_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:4'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(Product::class, 'ingredient_id');
    }
}
