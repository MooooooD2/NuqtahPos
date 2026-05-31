<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'code', 'address', 'phone', 'manager_id', 'is_default', 'is_active'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
