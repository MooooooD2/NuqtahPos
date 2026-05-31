<?php

namespace App\Contracts\Repositories;

interface SettingRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function getGroup(string $group): array;

    public function getAllGrouped(): array;

    public function forget(string $key): void;
}
