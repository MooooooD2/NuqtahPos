<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\PlanFeatureService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PlanController extends Controller
{
    use ApiResponse;

    private function guardMasterTenant(): void
    {
        $masterId = config('tenancy.master_tenant');
        $currentId = tenancy()->tenant?->id;
        if ($masterId && $currentId && $currentId !== $masterId) {
            abort(403, __('pos.master_tenant_only'));
        }
    }

    public function publicIndex()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['plans' => $plans]);
    }

    public function index()
    {
        $this->guardMasterTenant();

        $plans = Plan::orderBy('sort_order')->orderBy('monthly_price')->get();

        $tenantCounts = Tenant::whereIn('plan', $plans->pluck('id'))
            ->selectRaw('plan, COUNT(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan');

        $allModules = PlanFeatureService::allModules();
        $moduleGroups = PlanFeatureService::moduleGroups();

        return view('plans.index', compact('plans', 'tenantCounts', 'allModules', 'moduleGroups'));
    }

    public function indexApi()
    {
        $this->guardMasterTenant();

        $plans = Plan::orderBy('sort_order')->orderBy('monthly_price')->get();

        $tenantCounts = Tenant::whereIn('plan', $plans->pluck('id'))
            ->selectRaw('plan, COUNT(*) as count')
            ->groupBy('plan')
            ->pluck('count', 'plan');

        return $this->success([
            'plans' => $plans,
            'tenant_counts' => $tenantCounts,
            'all_modules' => PlanFeatureService::allModules(),
            'module_groups' => PlanFeatureService::moduleGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $this->guardMasterTenant();

        $data = $request->validate([
            'id' => 'required|string|max:50|alpha_dash|unique:plans,id',
            'name' => 'required|string|max:100',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'nullable|numeric|min:0',
            'trial_days' => 'required|integer|min:0|max:365',
            'max_users' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:200',
            'feature_flags' => 'nullable|array',
            'feature_flags.*' => 'string|max:50',
            'sort_order' => 'required|integer|min:0',
        ]);

        $data['id'] = strtolower($data['id']);

        // Only keep valid module keys
        if (! empty($data['feature_flags'])) {
            $validKeys = array_keys(PlanFeatureService::allModules());
            $data['feature_flags'] = array_values(array_intersect($data['feature_flags'], $validKeys));
        }

        $plan = Plan::create($data);

        Cache::forget("plan_features:{$plan->id}");

        return $this->success(['plan' => $plan], __('pos.plan_created'));
    }

    public function update(Request $request, string $id)
    {
        $this->guardMasterTenant();

        $plan = Plan::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'nullable|numeric|min:0',
            'trial_days' => 'required|integer|min:0|max:365',
            'max_users' => 'nullable|integer|min:1',
            'max_products' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:200',
            'feature_flags' => 'nullable|array',
            'feature_flags.*' => 'string|max:50',
            'sort_order' => 'required|integer|min:0',
        ]);

        // Only keep valid module keys
        if (isset($data['feature_flags'])) {
            $validKeys = array_keys(PlanFeatureService::allModules());
            $data['feature_flags'] = array_values(array_intersect($data['feature_flags'] ?? [], $validKeys));
        }

        $plan->update($data);

        Cache::forget("plan_features:{$id}");

        return $this->success(['plan' => $plan->fresh()], __('pos.plan_updated'));
    }

    public function toggle(string $id)
    {
        $this->guardMasterTenant();

        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => ! $plan->is_active]);

        return $this->success(['plan' => $plan->fresh()], __('pos.plan_updated'));
    }

    public function destroy(string $id)
    {
        $this->guardMasterTenant();

        $plan = Plan::findOrFail($id);

        if (Tenant::where('plan', $id)->exists()) {
            return $this->error(__('pos.plan_has_tenants'), 422);
        }

        $plan->delete();
        Cache::forget("plan_features:{$id}");

        return $this->success([], __('pos.plan_deleted'));
    }
}
