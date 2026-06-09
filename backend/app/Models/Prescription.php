<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $fillable = [
        'prescription_number', 'customer_id', 'patient_name', 'patient_phone',
        'doctor_name', 'doctor_phone', 'clinic_name',
        'issued_date', 'expiry_date', 'notes', 'status', 'dispensed_by',
    ];

    protected $casts = [
        'issued_date'  => 'date',
        'expiry_date'  => 'date',
    ];

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispenser()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}
