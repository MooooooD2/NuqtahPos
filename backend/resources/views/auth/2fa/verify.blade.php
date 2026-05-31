<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('pos.2fa_verify') ?? 'التحقق بخطوتين' }}</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css?v=' . filemtime(public_path('css/styles.css'))) }}">
</head>
<body class="auth-body">
<div class="auth-card">
    <h2 class="auth-title">🔐 التحقق بخطوتين</h2>
    <p class="auth-subtitle">أدخل الرمز المكون من 6 أرقام من تطبيق المصادقة</p>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('2fa.verify.post') }}">
        @csrf
        <div class="form-group">
            <input
                type="text"
                name="one_time_password"
                class="form-control text-center"
                placeholder="000000"
                maxlength="6"
                inputmode="numeric"
                pattern="[0-9]{6}"
                autofocus
                autocomplete="one-time-code"
            >
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3">تحقق</button>
    </form>

    <div class="mt-3 text-center">
        <a href="{{ route('2fa.recover') }}" class="text-muted small">استخدام رمز الاسترداد</a>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-2 text-center">
        @csrf
        <button type="submit" class="btn btn-link text-muted">تسجيل الخروج</button>
    </form>
</div>
</body>
</html>
