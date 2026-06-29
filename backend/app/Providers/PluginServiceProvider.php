<?php

namespace App\Providers;

use App\Services\PluginManager;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    /**
     * Deferred to boot() — registerEnabled() queries the `plugins` table,
     * and Eloquent's connection resolver isn't set until
     * DatabaseServiceProvider::boot() has run.
     */
    public function boot(): void
    {
        $this->app->make(PluginManager::class)->registerEnabled();
    }
}
