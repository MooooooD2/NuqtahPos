<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ app()->getLocale() === 'ar' ? 'انتهى الاشتراك' : 'Subscription Expired' }} — NuqtahPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style nonce="{{ app('csp-nonce') }}">
        * { font-family: 'Tajawal', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(145deg, #0f172a 0%, #1e3a8a 50%, #312e81 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
        }
        .card-expired {
            background: #fff;
            border-radius: 1.5rem;
            padding: 3rem 2.5rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 40px 80px rgba(0,0,0,.3);
        }
        .icon-ring {
            width: 88px; height: 88px;
            border-radius: 50%;
            background: #fff7ed;
            border: 3px solid #fed7aa;
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }
        .status-badge {
            display: inline-block;
            padding: .35rem 1rem;
            border-radius: 2rem;
            font-size: .8rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }
        .badge-expired   { background: #fef2f2; color: #dc2626; }
        .badge-suspended { background: #fafafa; color: #374151; }
        .badge-cancelled { background: #f9fafb; color: #6b7280; }
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: .65rem .9rem;
            background: #f8fafc;
            border-radius: .625rem;
            margin-bottom: .5rem;
            font-size: .88rem;
        }
        .info-row .label { color: #64748b; }
        .info-row .value { font-weight: 700; color: #0f172a; }
        .btn-renew {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #fff; border: none;
            padding: .85rem 2.5rem;
            border-radius: .75rem;
            font-weight: 800; font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            transition: opacity .2s, transform .2s;
        }
        .btn-renew:hover { opacity: .9; color: #fff; transform: translateY(-2px); }
        .btn-logout {
            color: #94a3b8; font-size: .85rem; text-decoration: none;
            display: block; margin-top: 1rem;
        }
        .btn-logout:hover { color: #475569; }
    </style>
</head>
<body>
@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="card-expired">
    <div class="icon-ring">
        @if($status === 'suspended')
            <i class="fas fa-pause-circle" style="color:#f59e0b"></i>
        @elseif($status === 'cancelled')
            <i class="fas fa-times-circle" style="color:#6b7280"></i>
        @else
            <i class="fas fa-clock" style="color:#ea580c"></i>
        @endif
    </div>

    @if($status === 'suspended')
        <span class="status-badge badge-suspended">
            <i class="fas fa-pause me-1"></i>{{ $isAr ? 'الحساب موقوف' : 'Account Suspended' }}
        </span>
    @elseif($status === 'cancelled')
        <span class="status-badge badge-cancelled">
            <i class="fas fa-ban me-1"></i>{{ $isAr ? 'الاشتراك ملغى' : 'Subscription Cancelled' }}
        </span>
    @else
        <span class="status-badge badge-expired">
            <i class="fas fa-exclamation-circle me-1"></i>{{ $isAr ? 'انتهى الاشتراك' : 'Subscription Expired' }}
        </span>
    @endif

    <h2 class="fw-900 mb-2" style="font-weight:900;font-size:1.6rem;color:#0f172a">
        @if($status === 'suspended')
            {{ $isAr ? 'تم إيقاف حسابك مؤقتاً' : 'Your Account is Suspended' }}
        @elseif($status === 'cancelled')
            {{ $isAr ? 'تم إلغاء اشتراكك' : 'Your Subscription Was Cancelled' }}
        @else
            {{ $isAr ? 'انتهت فترة اشتراكك' : 'Your Subscription Has Expired' }}
        @endif
    </h2>
    <p class="text-muted mb-4" style="font-size:.92rem;line-height:1.7">
        @if($status === 'suspended')
            {{ $isAr ? 'تواصل مع المسؤول لإعادة تفعيل حسابك.' : 'Please contact the administrator to reactivate your account.' }}
        @elseif($status === 'cancelled')
            {{ $isAr ? 'يمكنك تجديد اشتراكك للعودة إلى النظام.' : 'You can renew your subscription to regain access.' }}
        @else
            {{ $isAr ? 'انتهت فترة اشتراكك أو التجربة المجانية. جدّد اشتراكك الآن للاستمرار في استخدام النظام.' : 'Your trial or subscription period has ended. Renew now to continue using the system.' }}
        @endif
    </p>

    <div class="mb-4">
        <div class="info-row">
            <span class="label"><i class="fas fa-store me-2 text-primary"></i>{{ $isAr ? 'المتجر' : 'Store' }}</span>
            <span class="value">{{ $tenant->name }}</span>
        </div>
        <div class="info-row">
            <span class="label"><i class="fas fa-tag me-2 text-primary"></i>{{ $isAr ? 'الخطة' : 'Plan' }}</span>
            <span class="value">{{ strtoupper($tenant->plan ?? 'Basic') }}</span>
        </div>
        @if($tenant->trial_ends_at || $tenant->subscription_ends_at)
        <div class="info-row">
            <span class="label"><i class="fas fa-calendar me-2 text-danger"></i>{{ $isAr ? 'تاريخ الانتهاء' : 'Expired On' }}</span>
            <span class="value" style="color:#dc2626">
                {{ ($tenant->subscription_ends_at ?? $tenant->trial_ends_at)?->format('d M Y') }}
            </span>
        </div>
        @endif
    </div>

    <a href="mailto:support@NuqtahPOS.com" class="btn-renew">
        <i class="fas fa-redo me-2"></i>
        {{ $isAr ? 'تواصل لتجديد الاشتراك' : 'Contact to Renew Subscription' }}
    </a>

    <p class="mt-3 text-muted" style="font-size:.82rem">
        <i class="fas fa-headset me-1"></i>
        {{ $isAr ? 'فريق الدعم متاح على مدار الساعة' : '24/7 support available' }}
    </p>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:1.5rem">
        @csrf
        <button type="submit" class="btn-logout">
            <i class="fas fa-sign-out-alt me-1"></i>
            {{ $isAr ? 'تسجيل الخروج' : 'Sign Out' }}
        </button>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
