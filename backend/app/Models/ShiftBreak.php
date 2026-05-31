<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftBreak extends Model
{
    protected $fillable = ['employee_shift_id', 'started_at', 'ended_at', 'duration_minutes', 'type'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function end(): void
    {
        $duration = $this->started_at->diffInMinutes(now());
        $this->update(['ended_at' => now(), 'duration_minutes' => $duration]);
    }
}
