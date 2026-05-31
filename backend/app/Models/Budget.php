<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = ['year', 'month', 'type', 'category', 'amount', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
    ];
}
