<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('pos.register') }} - {{ __('pos.app_name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text x='50' y='50' text-anchor='middle' dominant-baseline='middle' font-size='80'>🏪</text></svg>">

    @if(app()->getLocale() === 'ar')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @else
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">

    <style @nonce>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: {{ app()->getLocale() === 'ar' ? "'Cairo', sans-serif" : "'Segoe UI', sans-serif" }};
        }
        .register-card {
            background: #fff;
            border-radius: 1rem;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
            width: 100%;
            max-width: 560px;
            margin: 2rem auto;
        }
        .register-logo {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.6rem; color: #fff;
        }
        .section-label {
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: .35rem;
            margin-bottom: 1rem;
        }
        .form-control:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }
        .btn-register {
            background: linear-gradient(135deg, #10b981, #059669);
            border: none; color: #fff; width: 100%;
            padding: .75rem; border-radius: .5rem;
            font-weight: 600; font-size: 1rem;
            transition: opacity .2s;
        }
        .btn-register:hover { opacity: .9; color: #fff; }
        .btn-register:active { transform: scale(.98); }
        .btn-register:disabled { opacity: .6; cursor: not-allowed; }
        .code-preview {
            font-family: monospace;
            font-size: .8rem;
            color: #059669;
            margin-top: .25rem;
        }
        .lang-switcher { position: fixed; top: 1rem; {{ app()->getLocale() === 'ar' ? 'left: 1rem;' : 'right: 1rem;' }} }
        @media (max-width: 768px) {
            .register-card { padding: 1.5rem; margin: 1rem; }
            .form-control, .btn-register { font-size: 16px !important; }
        }
    </style>
</head>
<body>
    <div class="lang-switcher">
        <a href="{{ route('lang.switch', 'ar') }}" class="btn btn-sm btn-outline-light">🇪🇬 AR</a>
        <a href="{{ route('lang.switch', 'en') }}" class="btn btn-sm btn-outline-light">🇺🇸 EN</a>
    </div>

    <div class="container">
        <div class="register-card mx-auto">
            <div class="register-logo"><i class="fas fa-store"></i></div>
            <h4 class="text-center fw-bold mb-1">{{ __('pos.create_your_store') }}</h4>
            <p class="text-center text-muted mb-4">{{ __('pos.register_subtitle') }}</p>

            <div id="alertBox" class="alert d-none" role="alert"></div>

            <form id="registerForm" autocomplete="on" novalidate>
                {{-- ── Store Information ──────────────────────────────────────── --}}
                <div class="section-label">{{ __('pos.store_information') }}</div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.store_name') }} *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-store"></i></span>
                        <input type="text" class="form-control" id="store_name" name="store_name"
                            placeholder="{{ __('pos.store_name_placeholder') }}"
                            autocomplete="organization" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        {{ __('pos.store_code') }} *
                        <span class="badge bg-secondary ms-1" style="font-size:.65rem">{{ __('pos.login_code') }}</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                        <input type="text" class="form-control" id="store_code" name="store_code"
                            placeholder="{{ __('pos.store_code_placeholder') }}"
                            style="direction:ltr" pattern="[a-zA-Z0-9_-]+" autocomplete="off" required>
                    </div>
                    <div class="code-preview" id="codePreview"></div>
                    <div class="form-text">{{ __('pos.store_code_hint') }}</div>
                </div>

                {{-- ── Admin Account ──────────────────────────────────────────── --}}
                <div class="section-label mt-3">{{ __('pos.admin_account') }}</div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.full_name') }} *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            placeholder="{{ __('pos.full_name_placeholder') }}"
                            autocomplete="name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.username') }} *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="reg_username" name="username"
                            placeholder="{{ __('pos.username_placeholder') }}"
                            style="direction:ltr" autocomplete="username" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('pos.password') }} *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="reg_password" name="password"
                            placeholder="{{ __('pos.password') }}"
                            autocomplete="new-password" required minlength="8">
                        <button class="btn btn-outline-secondary" type="button" id="togglePwdBtn">
                            <i class="fas fa-eye" id="eyeIcon1"></i>
                        </button>
                    </div>
                    <div class="form-text">{{ __('pos.password_min_hint') }}</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('pos.confirm_password') }} *</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="reg_password_confirmation"
                            name="password_confirmation"
                            placeholder="{{ __('pos.confirm_password') }}"
                            autocomplete="new-password" required>
                    </div>
                </div>

                <button class="btn-register btn" type="submit" id="registerBtn">
                    <span id="registerText"><i class="fas fa-rocket me-1"></i>{{ __('pos.create_store') }}</span>
                    <span id="registerSpinner" class="spinner-border spinner-border-sm ms-2 d-none"></span>
                </button>
            </form>

            <p class="text-center text-muted mt-3 mb-0" style="font-size:.9rem">
                {{ __('pos.already_have_account') }}
                <a href="{{ route('login') }}" class="text-decoration-none fw-semibold">{{ __('pos.login') }}</a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script @nonce>
    const REGISTER_URL = "{{ route('register.post') }}";

    /* Auto-generate store code from store name */
    document.getElementById('store_name').addEventListener('input', function () {
        const manual = document.getElementById('store_code').dataset.manual;
        if (!manual) {
            const slug = this.value.trim()
                .toLowerCase()
                .replace(/\s+/g, '_')
                .replace(/[^a-z0-9_-]/g, '')
                .slice(0, 30);
            document.getElementById('store_code').value = slug;
            updatePreview(slug);
        }
    });

    document.getElementById('store_code').addEventListener('input', function () {
        this.dataset.manual = '1';
        updatePreview(this.value.trim());
    });

    function updatePreview(code) {
        const el = document.getElementById('codePreview');
        el.textContent = code
            ? '{{ __('pos.login_code_will_be') }}: ' + code.toLowerCase()
            : '';
    }

    /* Toggle password visibility */
    document.getElementById('togglePwdBtn').addEventListener('click', function () {
        const p  = document.getElementById('reg_password');
        const ic = document.getElementById('eyeIcon1');
        p.type   = p.type === 'password' ? 'text' : 'password';
        ic.className = p.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        p.focus();
    });

    /* Form submit */
    let _busy = false;
    document.getElementById('registerForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (_busy) return;

        const alertBox = document.getElementById('alertBox');
        alertBox.classList.add('d-none');

        const storeName  = document.getElementById('store_name').value.trim();
        const storeCode  = document.getElementById('store_code').value.trim().toLowerCase();
        const fullName   = document.getElementById('full_name').value.trim();
        const username   = document.getElementById('reg_username').value.trim();
        const password   = document.getElementById('reg_password').value;
        const passConf   = document.getElementById('reg_password_confirmation').value;

        /* Client-side validation */
        if (!storeName || !storeCode || !fullName || !username || !password) {
            showAlert('{{ __('pos.fill_required_fields') }}', 'danger'); return;
        }
        if (!/^[a-zA-Z0-9_-]+$/.test(storeCode)) {
            showAlert('{{ __('pos.store_code_invalid') }}', 'danger'); return;
        }
        if (password.length < 8) {
            showAlert('{{ __('pos.password_too_short') }}', 'danger'); return;
        }
        if (password !== passConf) {
            showAlert('{{ __('pos.password_mismatch') }}', 'danger'); return;
        }

        setBusy(true);
        try {
            const res = await fetch(REGISTER_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ store_name: storeName, store_code: storeCode, full_name: fullName, username, password, password_confirmation: passConf }),
            });

            const data = await res.json();
            if (data.success) {
                showAlert('{{ __('pos.store_created_success') }}', 'success');
                setTimeout(() => { window.location.href = data.redirect; }, 800);
            } else {
                const msg = data.errors
                    ? Object.values(data.errors).flat().join(' ')
                    : (data.message || '{{ __('pos.registration_failed') }}');
                showAlert(msg, 'danger');
            }
        } catch (err) {
            showAlert('{{ __('pos.server_error') }}', 'danger');
        } finally {
            setBusy(false);
        }
    });

    function showAlert(msg, type) {
        const el = document.getElementById('alertBox');
        el.className = 'alert alert-' + type;
        el.textContent = msg;
        el.classList.remove('d-none');
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function setBusy(busy) {
        _busy = busy;
        document.getElementById('registerBtn').disabled = busy;
        document.getElementById('registerText').style.opacity = busy ? '.6' : '1';
        document.getElementById('registerSpinner').classList.toggle('d-none', !busy);
    }
    </script>
</body>
</html>
