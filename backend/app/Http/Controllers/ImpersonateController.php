<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImpersonateController extends Controller
{
    public function start(Request $request, User $user)
    {
        // SECURITY FIX: require manage_roles permission — previously any authenticated user
        // could call this endpoint and impersonate any other user in the tenant.
        $this->authorize('manage_roles');

        $admin = Auth::user();

        if ($admin->id === $user->id) {
            return back()->withErrors(['impersonate' => __('pos.impersonate_self_error')]);
        }

        // Prevent impersonating a user who has admin-level privileges — privilege escalation guard.
        if ($user->hasPermissionTo('manage_roles')) {
            return back()->withErrors(['impersonate' => __('pos.impersonate_admin_error')]);
        }

        $request->session()->put('impersonator_id', $admin->id);

        Log::channel('audit')->info('impersonate.start', [
            'admin_id' => $admin->id,
            'admin_name' => $admin->username,
            'target_id' => $user->id,
            'target_name' => $user->username,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');

        if (! $adminId) {
            return redirect()->route('dashboard');
        }

        $admin = User::find($adminId);

        if (! $admin || ! $admin->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        Auth::login($admin);

        return redirect()->route('dashboard');
    }
}
