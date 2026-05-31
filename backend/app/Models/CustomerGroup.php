<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    protected $fillable = ['name', 'description', 'discount_percent', 'price_level', 'is_active'];

    protected $casts = ['discount_percent' => 'decimal:4', 'is_active' => 'boolean'];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_group_id');
    }
}
