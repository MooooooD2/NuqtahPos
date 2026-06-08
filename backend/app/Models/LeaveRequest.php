<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id', 'leave_type', 'starts_at', 'ends_at',
        'days_count', 'reason', 'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'starts_at'   => 'date',
        'ends_at'     => 'date',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
