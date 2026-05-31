<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    private const SUPPORTED_LOCALES = ['ar', 'en'];

    /**
     * Switch application language
     */
    public function switch(string $locale)
    {
        // القائمة البيضاء — مضمونة من الـ route where clause أيضاً
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return redirect()->back();
        }

        App::setLocale($locale);
        Session::put('locale', $locale);
        Session::put('direction', $locale === 'ar' ? 'rtl' : 'ltr');

        if (auth()->check()) {
            auth()->user()->update(['language' => $locale]);
        }

        return redirect()->back();
    }

    /**
     * Get translations for JavaScript
     * FIX-04: منع Path Traversal
     */
    public function getTranslations(string $locale)
    {
        // FIX-04: قائمة بيضاء صارمة
        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            return response()->json([], 400);
        }

        // بناء المسار بعد التحقق — Laravel 11 uses lang/ at project root, not resources/lang/
        $translationFile = base_path('lang/' . $locale . '/pos.php');

        // تأكيد أن الملف داخل مجلد lang فعلاً (منع ../)
        $realBase = realpath(base_path('lang'));
        $realFile = realpath($translationFile);

        if ($realFile === false || ! str_starts_with($realFile, $realBase)) {
            return response()->json([], 403);
        }

        if (file_exists($realFile)) {
            $translations = include $realFile;

            return response()->json(is_array($translations) ? $translations : []);
        }

        return response()->json([]);
    }
}
