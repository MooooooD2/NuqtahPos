<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftSummary extends Model
{
    protected $fillable = [
        'employee_shift_id', 'total_sales', 'invoice_count',
        'cash_collected', 'card_collected', 'expected_cash',
        'cash_difference', 'cashier_note', 'supervisor_note',
    ];

    protected $casts = [
        'total_sales' => 'decimal:2',
        'cash_collected' => 'decimal:2',
        'card_collected' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function isBalanced(): bool
    {
        return abs((float) $this->cash_difference) < 0.01;
    }
}
