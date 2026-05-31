<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'payment_number', 'supplier_id', 'supplier_name',
        'amount', 'payment_method', 'payment_date',
        'notes', 'created_by', 'created_by_name',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:4'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
