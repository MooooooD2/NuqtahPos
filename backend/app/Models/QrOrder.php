<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrOrder extends Model
{
    protected $fillable = [
        'qr_table_id', 'invoice_id', 'kitchen_order_id',
        'customer_name', 'customer_phone', 'status', 'notes', 'total',
    ];

    public function qrTable(): BelongsTo
    {
        return $this->belongsTo(QrTable::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QrOrderItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
