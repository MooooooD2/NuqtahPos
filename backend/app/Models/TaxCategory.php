<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCategory extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'code', 'rate', 'is_default', 'is_active'];

    protected $casts = ['rate' => 'decimal:4', 'is_default' => 'boolean', 'is_active' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
