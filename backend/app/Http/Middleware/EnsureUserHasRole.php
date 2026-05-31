<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! auth()->check()) {
            abort(403, 'Unauthenticated.');
        }

        $user = auth()->user();

        if (empty($roles)) {
            if ($user->roles->count() === 0) {
                abort(403, 'You do not have any role assigned.');
            }
        } elseif (! $user->hasAnyRole($roles)) {
            abort(403, 'You do not have the required role.');
        }

        return $next($request);
    }
}
