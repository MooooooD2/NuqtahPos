<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'year', 'month', 'branch_id', 'status',
        'total_gross', 'total_deductions', 'total_net',
        'approved_by', 'approved_at', 'paid_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'paid_at'     => 'datetime',
    ];

    public function slips(): HasMany
    {
        return $this->hasMany(PayrollSlip::class, 'payroll_run_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
