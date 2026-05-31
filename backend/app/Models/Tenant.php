<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;

    protected $connection = 'mysql';

    protected $fillable = [
        'name', 'code', 'plan', 'is_active',
        'subscription_status', 'trial_ends_at', 'subscription_ends_at',
    ];

    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'code', 'plan', 'is_active', 'subscription_status', 'trial_ends_at', 'subscription_ends_at'];
    }

    protected $casts = [
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_status === 'active') {
            return ! $this->subscription_ends_at || $this->subscription_ends_at->isFuture();
        }
        if ($this->subscription_status === 'trial') {
            return ! $this->trial_ends_at || $this->trial_ends_at->isFuture();
        }

        return false;
    }

    public function subscriptionLabel(): string
    {
        return match ($this->subscription_status ?? 'trial') {
            'trial' => 'Trial',
            'active' => 'Active',
            'expired' => 'Expired',
            'cancelled' => 'Cancelled',
            'suspended' => 'Suspended',
            default => 'Unknown',
        };
    }

    public function subscriptionBadgeClass(): string
    {
        return match ($this->subscription_status ?? 'trial') {
            'trial' => 'warning',
            'active' => 'success',
            'expired' => 'danger',
            'cancelled' => 'secondary',
            'suspended' => 'dark',
            default => 'secondary',
        };
    }
}
