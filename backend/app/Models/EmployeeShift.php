<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Phase 2 — Employee Shift
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $branch_id
 * @property string $shift_date
 * @property string|null $clock_in_at
 * @property string|null $clock_out_at
 * @property float|null $hours_worked
 * @property float $overtime_hours
 * @property string $status
 */
class EmployeeShift extends Model
{
    protected $fillable = [
        'user_id', 'branch_id', 'shift_template_id', 'shift_date',
        'clock_in_at', 'clock_out_at', 'hours_worked', 'overtime_hours',
        'status', 'notes', 'opened_by', 'closed_by', 'meta',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'hours_worked' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'meta' => 'array',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ShiftBreak::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(ShiftSummary::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('shift_date', today());
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function clockIn(): void
    {
        $this->update([
            'clock_in_at' => now(),
            'status' => 'active',
        ]);
    }

    public function clockOut(): void
    {
        $clockIn = $this->clock_in_at;
        $clockOut = now();

        $totalMinutes = $clockIn ? $clockIn->diffInMinutes($clockOut) : 0;
        $breakMinutes = $this->breaks()->whereNotNull('ended_at')
            ->sum('duration_minutes');
        $workedMinutes = max(0, $totalMinutes - $breakMinutes);
        $standardMinutes = ($this->template->start_time ?? null)
            ? $this->template->end_time->diffInMinutes($this->template->start_time)
            : 480; // 8 hours default

        $overtime = max(0, $workedMinutes - $standardMinutes);

        $this->update([
            'clock_out_at' => $clockOut,
            'hours_worked' => round($workedMinutes / 60, 2),
            'overtime_hours' => round($overtime / 60, 2),
            'status' => 'completed',
        ]);
    }
}
