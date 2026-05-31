<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftTemplate extends Model
{
    protected $fillable = [
        'name', 'start_time', 'end_time', 'break_minutes', 'is_overnight', 'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'break_minutes' => 'integer',
        'is_overnight' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }

    public function durationHours(): float
    {
        return round(($this->break_minutes ?? 0) / 60, 2);
    }
}
