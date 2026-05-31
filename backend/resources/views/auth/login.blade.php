{{-- FILE: resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('pos.login') }} - {{ __('pos.app_name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text x='50' y='50' text-anchor='middle' dominant-baseline='middle' font-size='80'>🏪</text></svg>">

    @if(app()->getLocale() === 'ar')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    @else
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     @if (app()->getLocale() === 'ar')
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap"
            rel="stylesheet">
    @endif
        <link rel="stylesheet" href="{{ asset('css/styles.css') }}">

    <style @nonce>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: {{ app()->getLocale() === 'ar' ? "'Cairo', sans-serif" : "'Segoe UI', sans-serif" }};
        }
        .login-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
            width: 100%;
            max-width: 420px;
            margin: auto;
        }
        .login-logo {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.8rem; color: #fff;
        }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .btn-login {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none; color: #fff; width: 100%;
            padding: 0.75rem; border-radius: 0.5rem;
            font-weight: 600; font-size: 1rem;
            transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.9; color: #fff; }
        .btn-login:active { transform: scale(0.98); }
        .lang-switcher { position: fixed; top: 1rem; {{ app()->getLocale() === 'ar' ? 'left: 1rem;' : 'right: 1rem;' }} }
        
        /* Improve touch targets on mobile */
        @media (max-width: 768px) {
            .login-card { padding: 1.5rem; margin: 1rem; }
            .form-control, .btn-login, .input-group-text, .btn-outline-secondary { 
                font-size: 16px !important; /* Prevents zoom on focus in iOS */
            }
            .input-group button { min-height: 44px; }
            button, .btn { touch-action: manipulation; }
        }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="{{ route('lang.switch', 'ar') }}" class="btn btn-sm btn-outline-light">🇪🇬 AR</a>
        <a href="{{ route('lang.switch', 'en') }}" class="btn btn-sm btn-outline-light">🇺🇸 EN</a>
    </div>

    <div class="container">
        <div class="login-card mx-auto">
            <div class="login-logo"><i class="fas fa-cash-register"></i></div>
            <h4 class="text-center fw-bold mb-1">{{ __('pos.app_name') }}</h4>
            <p class="text-center text-muted mb-4">{{ __('pos.login') }}</p>

            <div id="alertBox" class="alert alert-danger d-none" role="alert"></div>

            <form id="loginForm" autocomplete="on" novalidate>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.tenant_code') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-store"></i></span>
                        <input type="text" class="form-control" id="tenant_code" name="tenant_code"
                            placeholder="{{ __('pos.tenant_code_placeholder') }}"
                            autocomplete="organization" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.username') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="{{ __('pos.username') }}" autocomplete="username" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pos.password') }}</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="{{ __('pos.password') }}" autocomplete="current-password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button class="btn-login btn" type="submit" id="loginBtn">
                    <span id="loginText">{{ __('pos.login') }}</span>
                    <span id="loginSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
            </form>

            <p class="text-center text-muted mt-3 mb-0" class="u-font-sm">
                {{ __('pos.no_account_yet') }}
                <a href="{{ route('register') }}" class="text-decoration-none fw-semibold">{{ __('pos.create_store') }}</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script @nonce>window.LOGIN_URL = "{{ route('login.post') }}";</script>
    <script src="{{ asset('js/login.js') }}"></script>
</body>
</html>