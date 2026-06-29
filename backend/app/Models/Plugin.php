<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $connection = 'mysql';

    protected $fillable = ['slug', 'name', 'version', 'is_enabled'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
