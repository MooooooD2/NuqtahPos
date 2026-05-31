<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.account_disabled'),
                ], 403);
            }

            return redirect()->route('login')->withErrors(['username' => __('auth.account_disabled')]);
        }

        return $next($request);
    }
}
