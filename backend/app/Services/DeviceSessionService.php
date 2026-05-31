<?php

namespace App\Services;

use App\Models\DeviceSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manages device/session tracking for security (Phase 6).
 */
class DeviceSessionService
{
    /**
     * Register or update a device session on login.
     */
    public function register(User $user, Request $request): DeviceSession
    {
        $ua = $request->userAgent() ?? '';

        // Mark all current sessions as not current
        DeviceSession::where('user_id', $user->id)->update(['is_current' => false]);

        // Detect device info
        [$browser, $os, $deviceType] = $this->parseUserAgent($ua);

        $token = Str::random(80);

        return DeviceSession::create([
            'user_id' => $user->id,
            'session_token' => $token,
            'device_name' => "{$browser} on {$os}",
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
            'ip_address' => $request->ip(),
            'last_active_at' => now(),
            'is_current' => true,
        ]);
    }

    /**
     * Update last_active_at for the current session.
     */
    public function touch(int $userId, string $token): void
    {
        DeviceSession::where('user_id', $userId)
            ->where('session_token', $token)
            ->whereNull('revoked_at')
            ->update(['last_active_at' => now()]);
    }

    /**
     * Revoke a specific session.
     */
    /**
     * Revoke a session. If $userId is null (admin bypass), ownership check is skipped.
     */
    public function revoke(int $sessionId, ?int $userId): bool
    {
        $query = DeviceSession::where('id', $sessionId)->whereNull('revoked_at');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $session = $query->first();

        if (! $session) {
            return false;
        }

        $session->revoke();

        return true;
    }

    /**
     * Revoke all sessions except current.
     */
    public function revokeAll(int $userId, ?string $exceptToken = null): int
    {
        $query = DeviceSession::where('user_id', $userId)->whereNull('revoked_at');

        if ($exceptToken) {
            $query->where('session_token', '!=', $exceptToken);
        }

        return $query->update(['revoked_at' => now()]);
    }

    /**
     * Get all active sessions for a user.
     */
    public function getActiveSessions(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return DeviceSession::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();
    }

    /**
     * Clean up old revoked/inactive sessions.
     */
    public function cleanup(int $daysOld = 30): int
    {
        return DeviceSession::where(function ($q) use ($daysOld) {
            $q->whereNotNull('revoked_at')
                ->orWhere('last_active_at', '<', now()->subDays($daysOld));
        })->delete();
    }

    /* ─── Private ────────────────────────────────────────────────────── */

    /**
     * Parse UA string into [browser, OS, deviceType].
     */
    private function parseUserAgent(string $ua): array
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari') => 'Safari',
            str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident') => 'Internet Explorer',
            default => 'Unknown Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows NT 10') => 'Windows 10',
            str_contains($ua, 'Windows NT 11') => 'Windows 11',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            default => 'Unknown OS',
        };

        $deviceType = match (true) {
            str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') => 'mobile',
            str_contains($ua, 'iPad') || str_contains($ua, 'Tablet') => 'tablet',
            default => 'desktop',
        };

        return [$browser, $os, $deviceType];
    }
}
