<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    use ApiResponse;

    private function guardMasterTenant(): void
    {
        $masterId = config('tenancy.master_tenant');
        $currentId = tenancy()->tenant?->id;

        if (! $masterId) {
            abort(403, 'Master tenant not configured.');
        }

        if ($currentId && $currentId !== $masterId) {
            abort(403, __('pos.master_tenant_only'));
        }
    }

    public function index()
    {
        $this->guardMasterTenant();
        $tenants = Tenant::orderBy('created_at')->get();

        $stats = [
            'total' => $tenants->count(),
            'active' => $tenants->where('subscription_status', 'active')->count(),
            'trial' => $tenants->where('subscription_status', 'trial')->count(),
            'expired' => $tenants->whereIn('subscription_status', ['expired', 'cancelled', 'suspended'])->count(),
        ];

        return view('tenants.index', compact('tenants', 'stats'));
    }

    public function cpanel()
    {
        $this->guardMasterTenant();

        $tenants = Tenant::orderBy('created_at')->get();
        $planModels = Plan::orderBy('sort_order')->get()->keyBy('id');
        // Fall back to 0 for any plan not yet in the DB
        $planPrices = $planModels->mapWithKeys(fn ($p) => [$p->id => $p->monthly_price])->toArray();

        // ── Revenue ──────────────────────────────────────────────────────
        $activeByPlan = $planModels->mapWithKeys(fn ($p) => [
            $p->id => $tenants->where('subscription_status', 'active')->where('plan', $p->id)->count(),
        ])->toArray();

        $mrr = array_sum(array_map(
            fn ($planId, $count) => ($planPrices[$planId] ?? 0) * $count,
            array_keys($activeByPlan),
            $activeByPlan,
        ));
        $arr = $mrr * 12;

        // ── Status breakdown ─────────────────────────────────────────────
        $statusCounts = [
            'trial' => $tenants->where('subscription_status', 'trial')->count(),
            'active' => $tenants->where('subscription_status', 'active')->count(),
            'expired' => $tenants->where('subscription_status', 'expired')->count(),
            'suspended' => $tenants->where('subscription_status', 'suspended')->count(),
            'cancelled' => $tenants->where('subscription_status', 'cancelled')->count(),
        ];

        // ── Monthly growth (last 12 months) ──────────────────────────────
        $monthlyGrowth = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $monthlyGrowth[] = [
                'label' => $month->format('M Y'),
                'count' => $tenants->filter(
                    fn ($t) => $t->created_at &&
                    $t->created_at->format('Y-m') === $month->format('Y-m'),
                )->count(),
            ];
        }

        // ── Expiring soon (30 days) ───────────────────────────────────────
        $expiringSoon = $tenants->filter(
            fn ($t) => ($t->subscription_ends_at || $t->trial_ends_at) &&
            ($t->subscription_ends_at ?? $t->trial_ends_at)->between(now(), now()->addDays(30)),
        )->sortBy(fn ($t) => $t->subscription_ends_at ?? $t->trial_ends_at);

        // ── Recent signups ────────────────────────────────────────────────
        $recentTenants = $tenants->sortByDesc('created_at')->take(5);

        return view('admin.cpanel', compact(
            'tenants',
            'mrr',
            'arr',
            'planModels',
            'planPrices',
            'activeByPlan',
            'statusCounts',
            'monthlyGrowth',
            'expiringSoon',
            'recentTenants',
        ));
    }

    public function store(Request $request)
    {
        $this->guardMasterTenant();

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:30|alpha_dash|unique:tenants,code',
            'plan' => 'nullable|string|in:basic,pro,enterprise',
            'trial_days' => 'nullable|integer|min:0|max:365',
        ]);

        $trialDays = $data['trial_days'] ?? 14;
        $tenant = Tenant::create([
            'name' => $data['name'],
            'code' => Str::lower($data['code']),
            'plan' => $data['plan'] ?? 'basic',
            'is_active' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays($trialDays),
        ]);
        // CreateDatabase + MigrateDatabase listeners fire automatically via TenancyServiceProvider

        return $this->success(['tenant' => $tenant], __('pos.tenant_created'));
    }

    public function update(Request $request, string $id)
    {
        $this->guardMasterTenant();

        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'plan' => 'nullable|string|in:basic,pro,enterprise',
        ]);

        $tenant->update($data);

        return $this->success(['tenant' => $tenant->fresh()], __('pos.tenant_updated'));
    }

    public function extend(Request $request, string $id)
    {
        $this->guardMasterTenant();

        $tenant = Tenant::findOrFail($id);
        $data = $request->validate([
            'months' => 'required|integer|min:1|max:24',
        ]);

        $base = ($tenant->subscription_ends_at && $tenant->subscription_ends_at->isFuture())
            ? $tenant->subscription_ends_at
            : Carbon::now();

        $tenant->update([
            'subscription_status' => 'active',
            'subscription_ends_at' => $base->addMonths($data['months']),
        ]);

        return $this->success(['tenant' => $tenant->fresh()], __('pos.subscription_extended'));
    }

    public function suspend(string $id)
    {
        $this->guardMasterTenant();

        if ($id === config('tenancy.master_tenant')) {
            return $this->error(__('pos.cannot_delete_master_tenant'), 422);
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['subscription_status' => 'suspended', 'is_active' => false]);

        return $this->success([], __('pos.subscription_suspended'));
    }

    public function cancelSubscription(string $id)
    {
        $this->guardMasterTenant();

        if ($id === config('tenancy.master_tenant')) {
            return $this->error(__('pos.cannot_delete_master_tenant'), 422);
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['subscription_status' => 'cancelled']);

        return $this->success([], __('pos.subscription_cancelled'));
    }

    public function stats()
    {
        $this->guardMasterTenant();

        $tenants = Tenant::all();
        $planRevenue = [
            'basic' => $tenants->where('plan', 'basic')->where('subscription_status', 'active')->count() * 49,
            'pro' => $tenants->where('plan', 'pro')->where('subscription_status', 'active')->count() * 99,
            'enterprise' => $tenants->where('plan', 'enterprise')->where('subscription_status', 'active')->count() * 199,
        ];

        return $this->success([
            'total' => $tenants->count(),
            'active' => $tenants->where('subscription_status', 'active')->count(),
            'trial' => $tenants->where('subscription_status', 'trial')->count(),
            'expired' => $tenants->where('subscription_status', 'expired')->count(),
            'suspended' => $tenants->where('subscription_status', 'suspended')->count(),
            'cancelled' => $tenants->where('subscription_status', 'cancelled')->count(),
            'mrr' => array_sum($planRevenue),
            'plan_revenue' => $planRevenue,
            'expiring_soon' => $tenants->filter(
                fn ($t) => $t->subscription_ends_at &&
                $t->subscription_ends_at->between(now(), now()->addDays(30)),
            )->count(),
        ]);
    }

    public function toggle(string $id)
    {
        $this->guardMasterTenant();

        if ($id === config('tenancy.master_tenant')) {
            return $this->error(__('pos.cannot_delete_master_tenant'), 422);
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => ! $tenant->is_active]);

        return $this->success([], $tenant->is_active ? __('pos.tenant_activated') : __('pos.tenant_deactivated'));
    }

    public function destroy(string $id)
    {
        $this->guardMasterTenant();

        $tenant = Tenant::findOrFail($id);

        // Prevent deleting the master tenant itself
        if ($id === config('tenancy.master_tenant')) {
            return $this->error(__('pos.cannot_delete_master_tenant'), 422);
        }

        $tenant->delete(); // DeleteDatabase listener fires automatically

        return $this->success([], __('pos.tenant_deleted'));
    }

    public function seed(string $id)
    {
        $this->guardMasterTenant();

        $tenant = Tenant::findOrFail($id);

        tenancy()->initialize($tenant);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder', '--force' => true]);
        tenancy()->end();

        // Re-initialize the master tenant so the response continues normally
        $master = Tenant::find(config('tenancy.master_tenant'));
        if ($master) {
            tenancy()->initialize($master);
        }

        return $this->success([], __('pos.tenant_seeded'));
    }

    public function tenantUsers(string $id)
    {
        $this->guardMasterTenant();

        $caller = tenancy()->tenant;           // remember who called
        $tenant = Tenant::findOrFail($id);

        tenancy()->initialize($tenant);

        $users = DB::table('users')
            ->select('id', 'full_name', 'username', 'is_active', 'created_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->full_name ?? '',
                'username' => $u->username ?? '',
                'is_active' => (bool) $u->is_active,
                'created_at' => $u->created_at,
            ]);

        // Restore the caller's tenant context
        if ($caller) {
            tenancy()->initialize($caller);
        } else {
            tenancy()->end();
        }

        return $this->success(['users' => $users]);
    }

    public function toggleTenantUser(string $tenantId, int $userId)
    {
        $this->guardMasterTenant();

        $caller = tenancy()->tenant;
        $tenant = Tenant::findOrFail($tenantId);

        tenancy()->initialize($tenant);

        $user = DB::table('users')->where('id', $userId)->first();
        if (! $user) {
            if ($caller) {
                tenancy()->initialize($caller);
            } else {
                tenancy()->end();
            }

            return $this->error('User not found', 404);
        }

        $newState = ! ((bool) $user->is_active);
        DB::table('users')->where('id', $userId)->update(['is_active' => $newState]);

        if ($caller) {
            tenancy()->initialize($caller);
        } else {
            tenancy()->end();
        }

        return $this->success(
            ['is_active' => $newState],
            $newState ? __('pos.user_activated') : __('pos.user_deactivated'),
        );
    }
}
