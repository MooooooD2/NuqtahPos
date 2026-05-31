<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeviceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'tenant_code' => 'required|string|max:50',
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:200',
        ]);

        // Per-account lockout: keyed by tenant+username so it survives across IPs
        $lockKey = 'login:' . strtolower($credentials['tenant_code']) . ':' . strtolower($credentials['username']);
        $maxAttempts = (int) config('security.login.max_attempts', 5);
        $decaySecs = (int) config('security.login.lockout_seconds', 900); // 15 minutes

        if (RateLimiter::tooManyAttempts($lockKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($lockKey);
            $this->writeAuthLog('auth.account_locked', null, $credentials['username'], $request, [
                'reason' => 'too_many_attempts',
                'retry_after' => $seconds,
            ]);

            return response()->json([
                'success' => false,
                'message' => __('auth.throttle', ['seconds' => $seconds]),
            ], 429);
        }

        $tenant = Tenant::where('code', strtolower($credentials['tenant_code']))->first();
        if (! $tenant) {
            RateLimiter::hit($lockKey, $decaySecs);
            $this->writeAuthLog('auth.login_failed', null, $credentials['username'], $request, [
                'reason' => 'tenant_not_found',
            ]);

            return response()->json(['success' => false], 401);
        }

        Tenancy::initialize($tenant);

        if (Auth::guard('web')->attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            try {
                $request->session()->put('tenant_id', $tenant->id);
                $request->session()->regenerate();
            } catch (Throwable) {
                // Stateless API request — no session available
            }

            RateLimiter::clear($lockKey);

            $user = Auth::user();
            $this->writeAuthLog('auth.login_success', (int) $user->id, $user->username, $request);

            // Phase 6: Track device session
            try {
                app(DeviceSessionService::class)->register($user, $request);
            } catch (Throwable) {
            }

            $token = $user->createToken('pos-spa', ['*'], now()->addHours(8))->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'username' => $user->username,
                    'role' => $user->role,
                    'language' => $user->language,
                ],
            ]);
        }

        $user = User::where('username', $credentials['username'])->first();

        // Always run Hash::check to prevent timing-based user enumeration.
        // The result is only acted upon if the user actually exists and is inactive.
        $passwordMatches = $user && Hash::check($credentials['password'], $user->password);

        if ($passwordMatches && ! $user->is_active) {
            RateLimiter::hit($lockKey, $decaySecs);
            $this->writeAuthLog('auth.login_blocked', (int) $user->id, $credentials['username'], $request, [
                'reason' => 'account_inactive',
            ]);

            // Return 401 (not 403) so the response doesn't reveal whether the username exists
            return response()->json([
                'success' => false,
                'message' => __('auth.failed'),
            ], 401);
        }

        RateLimiter::hit($lockKey, $decaySecs);
        $this->writeAuthLog('auth.login_failed', $user?->id ? (int) $user->id : null, $credentials['username'], $request, [
            'reason' => 'wrong_credentials',
        ]);

        return response()->json([
            'success' => false,
            'message' => __('auth.failed'),
        ], 401);
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        $username = Auth::user()?->username;

        Auth::logout();
        $request->session()->forget('tenant_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Log to file (context may no longer be available after Auth::logout)
        Log::channel('audit')->info('auth.logout', [
            'user_id' => $userId,
            'username' => $username,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Persist to DB as well
        try {
            AuditLog::create([
                'action' => 'auth.logout',
                'model' => User::class,
                'record_id' => (string) $userId,
                'user_id' => $userId,
                'username' => $username,
                'ip_address' => $this->sanitizeIp($request->ip()),
                'user_agent' => $this->sanitizeUa($request->userAgent()),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('auth.audit_db_failed', ['action' => 'auth.logout', 'error' => $e->getMessage()]);
        }

        return redirect()->route('login');
    }

    public function sessionInfo()
    {
        if (Auth::check()) {
            $user = Auth::user();

            return response()->json([
                'logged_in' => true,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
            ]);
        }

        return response()->json(['logged_in' => false]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function writeAuthLog(string $action, ?int $userId, string $username, Request $request, array $extra = []): void
    {
        $context = [
            'user_id' => $userId,
            'username' => $username,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
            ...$extra,
        ];

        // Audit writes must never crash the auth flow, but failures should be
        // surfaced to the application log so they are not silently lost.
        try {
            Log::channel('audit')->info($action, $context);
        } catch (Throwable $e) {
            Log::error('auth.audit_channel_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }

        try {
            AuditLog::create([
                'action' => $action,
                'model' => User::class,
                'record_id' => $userId ? (string) $userId : null,
                'user_id' => $userId,
                'username' => $username,
                'ip_address' => $this->sanitizeIp($request->ip()),
                'user_agent' => $this->sanitizeUa($request->userAgent()),
                'changes' => $extra ?: null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('auth.audit_db_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    private function sanitizeIp(?string $ip): string
    {
        if (! $ip) {
            return 'unknown';
        }

        return \filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'invalid';
    }

    private function sanitizeUa(?string $ua): string
    {
        if (! $ua) {
            return 'unknown';
        }

        return \substr(\preg_replace('/[\x00-\x1F\x7F]/', '', $ua), 0, 250);
    }
}
