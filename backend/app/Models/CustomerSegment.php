<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    protected $fillable = ['name', 'description', 'rules', 'customer_count', 'last_synced_at', 'is_active'];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];
}
