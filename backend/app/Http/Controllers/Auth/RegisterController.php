<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Branch;
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
