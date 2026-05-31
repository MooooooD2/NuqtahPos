<?php

namespace App\Repositories;

use App\Contracts\Repositories\SettingRepositoryInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingRepository implements SettingRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::set($key, $value);
    }

    public function getGroup(string $group): array
    {
        return Setting::getGroup($group);
    }

    public function getAllGrouped(): array
    {
        return Setting::getAllGrouped();
    }

    public function forget(string $key): void
    {
        Cache::forget('setting_' . $key);
    }
}
