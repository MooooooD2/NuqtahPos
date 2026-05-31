<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * FIX-5: 2FA TOTP for admin accounts.
 * Requires: composer require pragmarx/google2fa-laravel bacon/bacon-qr-code
 */
class TwoFactorController extends Controller
{
    public function showVerify()
    {
        if (! Auth::check() || ! Auth::user()->google2fa_enabled) {
            return redirect()->route('dashboard');
        }

        return view('auth.2fa.verify');
    }

    public function verify(Request $request)
    {
        $key = '2fa:' . Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['one_time_password' => __('pos.too_many_2fa_attempts', ['seconds' => $seconds])]);
        }

        $request->validate(['one_time_password' => 'required|digits:6']);

        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');
        $valid = $google2fa->verifyKey($user->google2fa_secret, $request->one_time_password);

        if (! $valid) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['one_time_password' => __('pos.2fa_invalid_code')]);
        }

        RateLimiter::clear($key);
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('dashboard'));
    }

    public function showSetup()
    {
        $user = Auth::user();
        $google2fa = app('pragmarx.google2fa');

        if ($user->google2fa_enabled) {
            return redirect()->route('dashboard');
        }

        $secret = $google2fa->generateSecretKey();
        session(['2fa_setup_secret' => $secret]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->username,
            $secret,
        );

        return view('auth.2fa.setup', compact('secret', 'qrCodeUrl'));
    }

    public function confirmSetup(Request $request)
    {
        $request->validate(['one_time_password' => 'required|digits:6']);

        $user = Auth::user();
        $secret = session('2fa_setup_secret');
        $google2fa = app('pragmarx.google2fa');

        if (! $secret || ! $google2fa->verifyKey($secret, $request->one_time_password)) {
            return back()->withErrors(['one_time_password' => __('pos.2fa_invalid_code')]);
        }

        $recoveryCodes = collect(range(1, 10))->map(fn () => Str::random(10))->toArray();
        $hashedCodes = array_map(fn ($code) => Hash::make($code), $recoveryCodes);

        $user->update([
            'google2fa_secret' => encrypt($secret),
            'google2fa_enabled' => true,
            'google2fa_recovery_codes' => $hashedCodes,
        ]);

        session()->forget('2fa_setup_secret');
        session(['2fa_passed' => true]);

        return view('auth.2fa.recovery-codes', compact('recoveryCodes'));
    }

    public function showRecover()
    {
        if (! Auth::check() || ! Auth::user()->google2fa_enabled) {
            return redirect()->route('dashboard');
        }

        return view('auth.2fa.recover');
    }

    public function recoverWithCode(Request $request)
    {
        $key = '2fa_recover:' . Auth::id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['recovery_code' => __('pos.too_many_2fa_attempts', ['seconds' => $seconds])]);
        }

        $request->validate(['recovery_code' => 'required|string']);

        $user = Auth::user();
        $hashedCodes = $user->google2fa_recovery_codes ?? [];
        $matchedIndex = null;

        foreach ($hashedCodes as $index => $hashed) {
            if (Hash::check($request->recovery_code, $hashed)) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['recovery_code' => __('pos.2fa_invalid_recovery_code')]);
        }

        // Consume the code — remove it so it cannot be reused
        array_splice($hashedCodes, $matchedIndex, 1);
        $user->update(['google2fa_recovery_codes' => $hashedCodes]);

        RateLimiter::clear($key);
        $request->session()->put('2fa_passed', true);

        return redirect()->intended(route('dashboard'));
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required']);

        // FIX: use Hash::check() instead of password_verify() — forward-compatible with Argon2 etc.
        if (! Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => __('pos.incorrect_password')]);
        }

        Auth::user()->update([
            'google2fa_secret' => null,
            'google2fa_enabled' => false,
            'google2fa_recovery_codes' => null,
        ]);

        $request->session()->forget('2fa_passed');

        return redirect()->route('dashboard')->with('success', __('pos.2fa_disabled'));
    }
}
