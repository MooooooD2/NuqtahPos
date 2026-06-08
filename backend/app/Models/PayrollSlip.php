<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSlip extends Model
{
    protected $fillable = [
        'payroll_run_id', 'user_id', 'basic_salary', 'total_allowances',
        'overtime_pay', 'bonus', 'gross_salary', 'income_tax',
        'social_insurance', 'other_deductions', 'absence_deduction',
        'late_deduction', 'net_salary', 'working_days', 'absent_days',
        'overtime_hours', 'breakdown', 'currency_code',
    ];

    protected $casts = [
        'breakdown' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
