<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class InitializeTenancyByToken
{
    public function handle(Request $request, Closure $next)
    {
        if (tenancy()->initialized) {
            return $next($request);
        }

        // Only activate for Bearer-token requests (SPA / mobile)
        if (! $request->bearerToken()) {
            return $next($request);
        }

        $code = $request->header('X-Tenant-Code', 'main');
        $tenant = Tenant::where('code', strtolower($code))
            ->where('is_active', true)
            ->first();

        if ($tenant) {
            tenancy()->initialize($tenant);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $next($request);
    }
}
