<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    protected $fillable = [
        'medicine_id', 'lot_number', 'quantity', 'expiry_date',
        'purchase_price', 'supplier_id', 'received_at', 'notes',
    ];

    protected $casts = [
        'expiry_date'    => 'date',
        'received_at'    => 'date',
        'purchase_price' => 'decimal:2',
    ];

    protected $appends = ['expiry_status', 'days_to_expiry'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getExpiryStatusAttribute(): string
    {
        $days = (int) now()->diffInDays($this->expiry_date, false);
        if ($days < 0)   return 'expired';
        if ($days <= 30) return 'critical';
        if ($days <= 90) return 'warning';
        return 'ok';
    }

    public function getDaysToExpiryAttribute(): int
    {
        return (int) now()->diffInDays($this->expiry_date, false);
    }
}
