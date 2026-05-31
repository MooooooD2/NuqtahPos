<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldInvoice extends Model
{
    protected $fillable = [
        'hold_number', 'cashier_id', 'cashier_name',
        'customer_id', 'customer_name',
        'cart_data', 'subtotal', 'discount_amount', 'total',
        'notes', 'status', 'expires_at',
    ];

    protected $casts = [
        'cart_data' => 'array',
        'subtotal' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
        'expires_at' => 'datetime',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
