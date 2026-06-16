<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class RegisterController extends Controller
{
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function apiRegister(Request $request)
    {
        // Creating a new tenant DB + running migrations + seeding can take
        // well over 60 s on a local machine. Raise the limit for this route.
        set_time_limit(300);

        $request->merge(['store_code' => strtolower($request->input('store_code', ''))]);

        // Use closure validators for central-DB tables (Tenant model has
        // $connection = 'mysql'). String-based rules like unique:tenants,code
        // resolve against the *default* connection, which the tenancy middleware
        // may have already switched to the tenant DB (via session cookie), so
        // the check would query a DB that has no `tenants` table, find no
        // duplicate, and pass — then the INSERT hits the real unique index.
        $data = $request->validate([
            'store_name' => 'required|string|max:100',
            'store_code' => ['required', 'string', 'max:30', 'alpha_dash',
                function ($attr, $value, $fail) {
                    if (Tenant::where('code', $value)->exists()) {
                        $fail('This store code is already taken. Please choose another.');
                    }
                },
            ],
            'full_name' => 'required|string|max:100',
            'username'  => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password'  => 'required|string|min:8|confirmed',
            'plan_id'       => ['nullable', 'string', 'max:50',
                function ($attr, $value, $fail) {
                    if ($value && ! Plan::where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected plan is invalid.');
                    }
                },
            ],
            'business_type' => ['nullable', 'string', 'in:retail,restaurant,pharmacy,contracting,general'],
        ], [
            'store_code.alpha_dash' => 'Store code may only contain letters, numbers, hyphens and underscores.',
            'password.confirmed'    => 'The passwords do not match.',
            'username.regex'        => 'Username may only contain letters, numbers and underscores.',
        ]);

        $tenant = null;

        try {
            $planId    = $data['plan_id'] ?? 'basic';
            $trialDays = Plan::where('id', $planId)->value('trial_days') ?? 14;

            $tenant = Tenant::create([
                'name'                => $data['store_name'],
                'code'                => Str::lower($data['store_code']),
                'plan'                => $planId,
                'business_type'       => $data['business_type'] ?? 'general',
                'is_active'           => true,
                'subscription_status' => 'trial',
                'trial_ends_at'       => now()->addDays($trialDays),
            ]);

            tenancy()->initialize($tenant);

            app(RolePermissionSeeder::class)->run();
            app(SettingsSeeder::class)->run();
            app(AccountSeeder::class)->run();

            Branch::where('code', 'MAIN')->update(['name' => $data['store_name']]);

            $adminRole = Role::where('name', 'admin')->firstOrFail();

            $user = User::create([
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'full_name' => $data['full_name'],
                'role' => 'admin',
                'is_active' => true,
                'language' => config('app.locale', 'en'),
            ]);

            // Role already has all permissions from RolePermissionSeeder.
            $user->syncRoles([$adminRole]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $token = $user->createToken('pos-spa', ['*'], now()->addHours(8))->plainTextToken;

            Log::channel('audit')->info('auth.register_success', [
                'tenant_id' => $tenant->id,
                'tenant_code' => $tenant->code,
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'username' => $user->username,
                    'role' => $user->role,
                    'language' => $user->language,
                    'permissions' => array_values($user->getAllPermissions()->pluck('name')->toArray()),
                ],
            ]);

        } catch (Throwable $e) {
            Log::error('API Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant?->id,
            ]);

            if ($tenant?->id) {
                // Always end tenancy first so subsequent queries use the central DB.
                try { tenancy()->end(); } catch (Throwable) {}

                // Delete the tenant DB (fires DeleteDatabase event).
                // If the DB drop fails we still want to remove the central record.
                try { $tenant->delete(); } catch (Throwable) {
                    // DB drop failed — force-delete the central record directly
                    // so the unique constraint is freed and the user can retry.
                    try {
                        Tenant::on('mysql')->where('id', $tenant->id)->delete();
                    } catch (Throwable) {}
                }
            }

            // Always expose the real message so the developer can debug.
            // The message is safe to return – it contains no sensitive data.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Registration failed. Please try again.',
            ], 500);
        }
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'store_name' => 'required|string|max:100',
            'store_code' => ['required', 'string', 'max:30', 'alpha_dash',
                // Uniqueness checked on central DB (no tenant initialized yet)
                'unique:tenants,code'],
            'full_name' => 'required|string|max:100',
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $tenant = null;

        try {
            // ── 1. Create tenant record in central DB ─────────────────────────
            // This fires TenantCreated → CreateDatabase + MigrateDatabase listeners
            $tenant = Tenant::create([
                'name' => $data['store_name'],
                'code' => Str::lower($data['store_code']),
                'plan' => 'basic',
                'is_active' => true,
            ]);

            // ── 2. Switch default DB connection to tenant database ────────────
            tenancy()->initialize($tenant);

            // ── 3. Seed required data into the tenant database ────────────────
            app(RolePermissionSeeder::class)->run();
            app(SettingsSeeder::class)->run();
            app(AccountSeeder::class)->run();

            // ── 4. Rename the default branch/warehouse created by migrations ──
            Branch::where('code', 'MAIN')->update(['name' => $data['store_name']]);

            // ── 5. Create first admin user ────────────────────────────────────
            $adminRole = Role::where('name', 'admin')->firstOrFail();
            $allPerms = Permission::all();

            $user = User::create([
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'full_name' => $data['full_name'],
                'role' => 'admin',
                'is_active' => true,
                'language' => config('app.locale', 'en'),
            ]);

            $user->syncRoles([$adminRole]);
            $user->givePermissionTo($allPerms);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            // ── 6. Authenticate the new admin ─────────────────────────────────
            Auth::login($user);
            $request->session()->regenerate();

            $request->session()->put('tenant_id', $tenant->id);
            Log::channel('audit')->info('auth.register_success', [
                'tenant_id' => $tenant->id,
                'tenant_code' => $tenant->code,
                'user_id' => $user->id,
                'username' => $user->username,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            // End tenancy so the session is written back to the central DB
            // (StartSession saves session after the middleware pipeline unwinds)
            // tenancy()->end();

            return response()->json([
                'success' => true,
                'redirect' => route('dashboard'),
            ]);

        } catch (Throwable $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant?->id,
            ]);

            // Clean up the half-created tenant if it exists
            if ($tenant?->id) {
                try {
                    tenancy()->end();
                    $tenant->delete(); // fires DeleteDatabase
                } catch (Throwable) {
                }
            }

            return response()->json([
                'success' => false,
                'message' => __('pos.registration_failed'),
            ], 500);
        }
    }
}
