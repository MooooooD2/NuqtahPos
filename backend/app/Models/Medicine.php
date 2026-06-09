<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $fillable = [
        'name_ar', 'name_en', 'generic_name', 'category', 'dosage_form',
        'strength', 'unit', 'barcode', 'manufacturer',
        'requires_prescription', 'controlled_drug',
        'reorder_level', 'selling_price', 'cost_price', 'notes', 'is_active',
    ];

    protected $casts = [
        'requires_prescription' => 'boolean',
        'controlled_drug'       => 'boolean',
        'is_active'             => 'boolean',
        'selling_price'         => 'decimal:2',
        'cost_price'            => 'decimal:2',
    ];

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function getTotalStockAttribute(): int
    {
        return (int) $this->batches()->where('quantity', '>', 0)->sum('quantity');
    }

    public function getNearestExpiryAttribute(): ?string
    {
        return $this->batches()
            ->where('quantity', '>', 0)
            ->where('expiry_date', '>=', today())
            ->min('expiry_date');
    }
}
