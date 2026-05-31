<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kitchen Display System — Order model
 *
 * @property int $id
 * @property int|null $invoice_id
 * @property int|null $branch_id
 * @property string $order_number
 * @property string|null $table_number
 * @property string $order_type
 * @property string $status
 * @property string|null $notes
 * @property \Carbon\Carbon|null $accepted_at
 * @property \Carbon\Carbon|null $ready_at
 * @property \Carbon\Carbon|null $served_at
 * @property int|null $assigned_to
 */
class KitchenOrder extends Model
{
    protected $fillable = [
        'invoice_id', 'branch_id', 'order_number', 'table_number',
        'order_type', 'status', 'notes', 'accepted_at', 'ready_at',
        'served_at', 'assigned_to',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
    ];

    /* ─── Relationships ─────────────────────────────────────────────── */

    public function items(): HasMany
    {
        return $this->hasMany(KitchenOrderItem::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /* ─── Scopes ─────────────────────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['served', 'cancelled']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /* ─── Computed ───────────────────────────────────────────────────── */

    public function getElapsedMinutesAttribute(): int
    {
        return (int) $this->created_at->diffInMinutes(now());
    }

    public function getIsUrgentAttribute(): bool
    {
        return $this->elapsed_minutes >= 15 && ! in_array($this->status, ['ready', 'served', 'cancelled']);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'preparing' => 'info',
            'ready' => 'success',
            'served' => 'secondary',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }
}
