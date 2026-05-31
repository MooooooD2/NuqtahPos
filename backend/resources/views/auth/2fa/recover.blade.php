<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استرداد الحساب</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css?v=' . filemtime(public_path('css/styles.css'))) }}">
</head>
<body class="auth-body">
<div class="auth-card">
    <h2 class="auth-title">🔑 استرداد الحساب</h2>
    <p class="auth-subtitle">أدخل أحد رموز الاسترداد التي حصلت عليها عند تفعيل التحقق بخطوتين</p>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('2fa.recover.post') }}">
        @csrf
        <div class="form-group">
            <input
                type="text"
                name="recovery_code"
                class="form-control"
                placeholder="xxxxxxxxxx"
                autofocus
                autocomplete="off"
            >
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-3">استرداد الوصول</button>
    </form>

    <div class="mt-3 text-center">
        <a href="{{ route('2fa.verify') }}" class="text-muted small">العودة إلى التحقق بالرمز</a>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-2 text-center">
        @csrf
        <button type="submit" class="btn btn-link text-muted">تسجيل الخروج</button>
    </form>
</div>
</body>
</html>
