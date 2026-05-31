<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Session takes priority
        if (Session::has('locale')) {
            $locale = Session::get('locale');
        }
        // 2. ✅ FIX: Safely read language from user (column now exists)
        elseif (auth()->check() && ! empty(auth()->user()->language)) {
            $locale = auth()->user()->language;
        }
        // 3. Default from config
        else {
            $locale = config('app.locale', 'ar');
        }

        // Validate — only allow ar or en
        if (in_array($locale, ['ar', 'en'])) {
            App::setLocale($locale);
            session(['direction' => $locale === 'ar' ? 'rtl' : 'ltr']);
        }

        return $next($request);
    }
}
