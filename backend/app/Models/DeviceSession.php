<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks active login devices for each user (Phase 6 — Security Hardening).
 *
 * @property int $id
 * @property int $user_id
 * @property string $session_token
 * @property string|null $device_name
 * @property string|null $device_type
 * @property string|null $browser
 * @property string|null $os
 * @property string|null $ip_address
 * @property \Carbon\Carbon|null $last_active_at
 * @property \Carbon\Carbon|null $revoked_at
 * @property bool $is_current
 */
class DeviceSession extends Model
{
    protected $fillable = [
        'user_id', 'session_token', 'device_name', 'device_type',
        'browser', 'os', 'ip_address', 'location',
        'last_active_at', 'revoked_at', 'is_current',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->revoked_at);
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }
}
