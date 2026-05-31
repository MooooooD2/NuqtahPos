<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * #36 تأمين الـ Session — منع Session Fixation + اكتشاف Hijacking
 * FIX-6: مزامنة مدة انتهاء الجلسة مع SESSION_LIFETIME في .env
 */
class SessionSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $session = $request->session();

            // #36 تخزين بصمة المتصفح عند أول تسجيل دخول
            if (! $session->has('_fingerprint')) {
                $session->put('_fingerprint', $this->fingerprint($request));
                $session->put('_login_ip', $request->ip());
                $session->put('_login_at', now()->timestamp);
            }

            // #36 اكتشاف Hijacking — بصمة مختلفة = جلسة مسروقة
            if ($session->get('_fingerprint') !== $this->fingerprint($request)) {
                Log::channel('audit')->warning('session_hijack_attempt', [
                    'user_id' => Auth::id(),
                    'username' => Auth::user()?->username ?? 'unknown',
                    'ip' => $request->ip(),
                    'stored_fp' => $session->get('_fingerprint'),
                    'current_fp' => $this->fingerprint($request),
                    'timestamp' => now()->toIso8601String(),
                ]);
                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'انتهت صلاحية الجلسة لأسباب أمنية. يرجى تسجيل الدخول مجدداً.');
            }

            // FIX-6: قراءة مدة الجلسة من SESSION_LIFETIME في .env بدلاً من القيمة الثابتة
            // SESSION_LIFETIME بالدقائق في Laravel — نحوله لثواني للمقارنة
            $sessionLifetimeSeconds = (int) config('session.lifetime', 480) * 60;

            $lastActivity = $session->get('_last_activity', now()->timestamp);
            if (now()->timestamp - $lastActivity > $sessionLifetimeSeconds) {
                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return redirect()->route('login')->with('error', 'انتهت جلستك. يرجى تسجيل الدخول مجدداً.');
            }

            $session->put('_last_activity', now()->timestamp);
        }

        return $next($request);
    }

    private function fingerprint(Request $request): string
    {
        // Only use IP subnet (first 3 octets) and Accept-Language.
        // User-Agent is excluded: it changes on browser updates causing false session invalidations,
        // and it can be trivially spoofed by an attacker anyway.
        $data = implode('::', [
            implode('.', array_slice(explode('.', $request->ip()), 0, 3)),
            $request->header('Accept-Language', ''),
        ]);

        return hash_hmac('sha256', $data, config('app.key'));
    }
}
