<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

class UnlockLoginCommand extends Command
{
    protected $signature   = 'auth:unlock {tenant_code} {username}';
    protected $description = 'Clear the login rate-limit lock for a specific tenant+username';

    public function handle(): int
    {
        $key = 'login:' . strtolower($this->argument('tenant_code'))
                        . ':' . strtolower($this->argument('username'));

        RateLimiter::clear($key);

        $this->info("Rate limit cleared for [{$this->argument('tenant_code')}] {$this->argument('username')}");

        return self::SUCCESS;
    }
}
