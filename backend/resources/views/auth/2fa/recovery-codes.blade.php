<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رموز الاسترداد</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css?v=' . filemtime(public_path('css/styles.css'))) }}">
</head>
<body class="auth-body">
<div class="auth-card" class="u-mw-480">
    <h2 class="auth-title">✅ تم تفعيل التحقق بخطوتين</h2>

    <div class="alert alert-warning">
        <strong>احفظ هذه الرموز الآن.</strong> لن تظهر مرة أخرى.
        كل رمز يُستخدم مرة واحدة فقط للدخول عند فقدان الجهاز.
    </div>

    <div class="recovery-codes">
        @foreach ($recoveryCodes as $code)
            <div class="recovery-code">{{ $code }}</div>
        @endforeach
    </div>

    <a href="{{ route('dashboard') }}" class="btn btn-primary w-100 mt-4">
        الانتقال للوحة التحكم
    </a>
</div>
</body>
</html>
