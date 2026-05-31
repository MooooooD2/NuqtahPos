<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    protected $connection = 'mysql'; // central DB

    protected $fillable = [
        'method', 'account_number', 'account_name',
        'notes', 'icon', 'color', 'label_ar', 'label_en',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function configured(): Collection
    {
        return static::where('is_active', true)
            ->whereNotNull('account_number')
            ->where('account_number', '!=', '')
            ->orderBy('sort_order')
            ->get();
    }

    public static function all_active(): Collection
    {
        return static::orderBy('sort_order')->get();
    }
}
