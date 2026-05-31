<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscription:expire';

    protected $description = 'Mark expired trials and subscriptions as expired';

    public function handle(): int
    {
        $now = now();

        // Trials that ended
        $trials = Tenant::where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        foreach ($trials as $tenant) {
            $tenant->update(['subscription_status' => 'expired']);
            $this->line("Trial expired: {$tenant->name} ({$tenant->id})");
        }

        // Active subscriptions that ended
        $subs = Tenant::where('subscription_status', 'active')
            ->whereNotNull('subscription_ends_at')
            ->where('subscription_ends_at', '<', $now)
            ->get();

        foreach ($subs as $tenant) {
            $tenant->update(['subscription_status' => 'expired']);
            $this->line("Subscription expired: {$tenant->name} ({$tenant->id})");
        }

        $total = $trials->count() + $subs->count();
        $this->info("Done. {$total} tenant(s) marked as expired.");

        return self::SUCCESS;
    }
}
