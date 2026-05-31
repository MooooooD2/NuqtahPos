<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = $this->allowedIps();

        // Empty whitelist = feature disabled, allow all
        if (empty($allowed)) {
            return $next($request);
        }

        $clientIp = $request->ip();

        foreach ($allowed as $entry) {
            if ($this->matches($clientIp, trim($entry))) {
                return $next($request);
            }
        }

        Log::channel('audit')->warning('ip_whitelist.blocked', [
            'ip' => $clientIp,
            'path' => $request->path(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ]);

        abort(403, 'Access denied: your IP address is not whitelisted.');
    }

    private function allowedIps(): array
    {
        $raw = Cache::remember('ip_whitelist', 300, function () {
            return Setting::where('key', 'ip_whitelist')->value('value') ?? '';
        });

        if (empty(trim($raw))) {
            return [];
        }

        return array_filter(array_map('trim', explode(',', $raw)));
    }

    private function matches(string $ip, string $entry): bool
    {
        if ($ip === $entry) {
            return true;
        }

        // Wildcard prefix (IPv4 only): 192.168.1.*
        if (str_ends_with($entry, '.*')) {
            $prefix = rtrim($entry, '.*');

            return str_starts_with($ip, $prefix . '.');
        }

        // CIDR range: supports both IPv4 (10.0.0.0/8) and IPv6 (2001:db8::/32)
        if (str_contains($entry, '/')) {
            return $this->ipInCidr($ip, $entry);
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $prefixLen] = explode('/', $cidr, 2);
        $prefixLen = (int) $prefixLen;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        // inet_pton returns false for invalid addresses; also both must be the same family
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = strlen($ipBin) * 8; // 32 for IPv4, 128 for IPv6
        $prefixLen = min($prefixLen, $bits);

        // Compare only the prefix bits by masking both addresses
        $fullBytes = intdiv($prefixLen, 8);
        $remainder = $prefixLen % 8;

        if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = 0xFF & (0xFF << (8 - $remainder));

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }
}
