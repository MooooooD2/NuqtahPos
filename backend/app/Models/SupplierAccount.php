<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAccount extends Model
{
    protected $fillable = [
        'supplier_id', 'transaction_type', 'reference_id',
        'reference_number', 'debit', 'credit', 'balance',
        'notes', 'created_by',
    ];

    protected $casts = ['debit' => 'decimal:4', 'credit' => 'decimal:4', 'balance' => 'decimal:4'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
