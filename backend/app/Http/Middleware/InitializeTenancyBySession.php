<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class InitializeTenancyBySession
{
    public function handle(Request $request, Closure $next)
    {
        if (tenancy()->initialized) {
            return $next($request);
        }

        $tenantId = $request->session()->get('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if ($tenant && $tenant->is_active) {
                tenancy()->initialize($tenant);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                // Verify the session user actually exists in this tenant's database.
                // Prevents cross-tenant access if session tenant_id is tampered with.
                $userId = $request->session()->get(Auth::guard('web')->getName());
                if ($userId !== null && ! User::where('id', $userId)->exists()) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login');
                }
            } elseif ($tenant && ! $tenant->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['tenant' => __('pos.tenant_deactivated')]);
            } else {
                // Tenant deleted — clear stale session
                $request->session()->forget('tenant_id');
            }
        }

        return $next($request);
    }
}
