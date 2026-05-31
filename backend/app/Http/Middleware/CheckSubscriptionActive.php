<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionActive
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = tenancy()->tenant;

        if (! $tenant) {
            return $next($request);
        }

        $masterId = config('tenancy.master_tenant');
        if ($masterId && $tenant->id === $masterId) {
            return $next($request);
        }

        if (! $tenant->isSubscriptionActive()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => __('pos.subscription_expired_message'),
                ], 402);
            }

            // Allow the subscribe page itself to avoid redirect loop
            if ($request->routeIs('subscribe')) {
                return $next($request);
            }

            return redirect()->route('subscribe');
        }

        return $next($request);
    }
}
