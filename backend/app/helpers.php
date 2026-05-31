<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get a setting value from the settings table.
     * Delegates to Setting::get() which caches results for 1 hour.
     */
    function setting(string $key, $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}
