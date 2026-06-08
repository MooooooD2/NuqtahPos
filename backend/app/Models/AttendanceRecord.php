<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'user_id', 'branch_id', 'work_date', 'check_in', 'check_out',
        'hours_worked', 'overtime_hours', 'status', 'late_minutes',
        'notes', 'check_in_method', 'location',
    ];

    protected $casts = [
        'work_date' => 'date',
        'check_in'  => 'datetime',
        'check_out' => 'datetime',
        'location'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
