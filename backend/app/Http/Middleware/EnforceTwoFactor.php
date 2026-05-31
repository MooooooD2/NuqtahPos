<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnforceTwoFactor
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        // Check if user is logged in and needs 2FA
        if ($user && $user->google2fa_enabled && ! $request->session()->has('2fa_passed')) {

            // CRITICAL: Do not redirect if already on the 2FA verification or recovery pages
            // FIX: route prefix is '2fa/*', NOT 'auth/2fa*'; route name is '2fa.verify' not 'auth.2fa.verify'
            if (! $request->is('2fa*')) {
                return redirect()->route('2fa.verify');
            }
        }

        return $next($request);
    }
}
