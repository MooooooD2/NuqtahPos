<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'invoice_number', 'offline_uuid',
        'total', 'discount', 'loyalty_points_used', 'loyalty_discount',
        'tax_rate', 'tax_amount', 'final_total',
        'cash_received', 'change_amount',
        'payment_method', 'is_split_payment',
        'cashier_id', 'cashier_name', 'customer_id',
        'branch_id', 'warehouse_id',
        'status', 'date',
        'eta_uuid', 'eta_long_id', 'eta_submission_id', 'eta_status',
        'eta_response', 'eta_submitted_at', 'eta_hash',
    ];

    protected $hidden = ['cashier_id'];

    protected $casts = [
        'total' => 'decimal:4',
        'final_total' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'loyalty_points_used' => 'integer',
        'loyalty_discount' => 'decimal:4',
        'cash_received' => 'decimal:4',
        'change_amount' => 'decimal:4',
        'is_split_payment' => 'boolean',
        'date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function returns()
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
