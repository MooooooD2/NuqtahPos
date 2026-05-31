<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد التحقق بخطوتين</title>
    <link rel="stylesheet" href="{{ asset('css/styles.css?v=' . filemtime(public_path('css/styles.css'))) }}">
</head>
<body class="auth-body">
<div class="auth-card" class="u-mw-480">
    <h2 class="auth-title">🔐 إعداد التحقق بخطوتين</h2>

    <p>1. افتح تطبيق Google Authenticator أو Authy</p>
    <p>2. امسح رمز QR أو أدخل المفتاح يدوياً</p>

    <div class="text-center my-3">
        {!! QrCode::size(200)->generate($qrCodeUrl) !!}
    </div>

    <div class="alert alert-info text-center">
        <small>المفتاح اليدوي: <strong>{{ $secret }}</strong></small>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('2fa.setup.confirm') }}">
        @csrf
        <div class="form-group">
            <label>أدخل الرمز للتأكيد</label>
            <input
                type="text"
                name="one_time_password"
                class="form-control text-center"
                placeholder="000000"
                maxlength="6"
                inputmode="numeric"
                autofocus
            >
        </div>
        <button type="submit" class="btn btn-success w-100 mt-3">تفعيل</button>
    </form>
</div>
</body>
</html>
