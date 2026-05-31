<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PlanFeatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate access to a route based on the tenant's plan feature flags.
 *
 * Usage in routes:
 *   ->middleware('planFeature:hr_module')
 *   ->middleware('planFeature:hr_module,payroll')   // ALL must be present
 *
 * When the feature is absent the user gets:
 *   - JSON 403 with upgrade_required:true  (API / XHR)
 *   - Redirect to /subscribe with a flash message  (browser)
 */
class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        // Master tenant always passes
        $tenant = tenancy()->tenant;
        $masterId = config('tenancy.master_tenant');
        if ($masterId && $tenant?->id === $masterId) {
            return $next($request);
        }

        foreach ($features as $feature) {
            if (! PlanFeatureService::has($feature)) {
                $message = __('pos.feature_not_in_plan', ['feature' => $feature]);

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'upgrade_required' => true,
                    ], Response::HTTP_FORBIDDEN);
                }

                return redirect()->route('subscribe')
                    ->with('error', $message)
                    ->with('upgrade_required', true);
            }
        }

        return $next($request);
    }
}
