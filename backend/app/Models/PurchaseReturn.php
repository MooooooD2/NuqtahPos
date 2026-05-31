<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'return_number', 'purchase_order_id', 'supplier_id', 'supplier_name',
        'total_amount', 'reason', 'refund_method', 'status',
        'processed_by', 'processed_by_name', 'return_date',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'return_date' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
