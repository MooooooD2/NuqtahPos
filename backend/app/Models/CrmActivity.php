<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CRM Activity — interactions with customers (Phase 8).
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $user_id
 * @property string $type
 * @property string|null $subject
 * @property string|null $notes
 * @property string $outcome
 * @property \Carbon\Carbon|null $scheduled_at
 * @property \Carbon\Carbon|null $completed_at
 */
class CrmActivity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'user_id', 'type', 'subject', 'notes',
        'outcome', 'scheduled_at', 'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePendingFollowUps($query)
    {
        return $query->where('outcome', 'pending')
            ->whereNotNull('scheduled_at')
            ->whereNull('completed_at');
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'call' => '📞',
            'email' => '📧',
            'visit' => '🏪',
            'whatsapp' => '💬',
            'note' => '📝',
            'complaint' => '⚠️',
            'follow_up' => '🔔',
            'sale' => '💰',
            'return' => '↩️',
            default => '•',
        };
    }
}
