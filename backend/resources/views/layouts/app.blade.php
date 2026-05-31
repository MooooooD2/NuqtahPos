{{-- FILE: resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="csp-nonce" content="{{ app('csp-nonce') ?? '' }}">
    <title>@yield('title', isset($branding) && $branding?->app_name ? $branding->app_name : __('pos.app_name'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <link rel="manifest" href="{{ route('manifest') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    {{-- Favicon: custom brand favicon or default emoji --}}
    @if(isset($branding) && $branding?->favicon_path)
        <link rel="icon" href="{{ Storage::url($branding->favicon_path) }}">
    @else
        <link rel="icon"
            href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text x='50' y='50' text-anchor='middle' dominant-baseline='middle' font-size='80'>🏪</text></svg>">
    @endif

    {{-- Bootstrap RTL/LTR --}}
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    @else
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    @endif

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    {{-- SweetAlert2 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    {{-- Font: white-label font or Arabic default --}}
    @php
        $_wlFont = $branding?->font_family ?? null;
        $_fontMap = [
            'Tajawal'              => 'https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap',
            'Cairo'                => 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap',
            'IBM Plex Sans Arabic' => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;700&display=swap',
            'Inter'                => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
            'Roboto'               => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
            'Poppins'              => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
        ];
        // If no brand font chosen, default to Cairo for Arabic locale
        if (!$_wlFont && app()->getLocale() === 'ar') {
            $_wlFont = 'Cairo';
        }
    @endphp
    @if($_wlFont && isset($_fontMap[$_wlFont]))
        <link href="{{ $_fontMap[$_wlFont] }}" rel="stylesheet">
    @endif

    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ filemtime(public_path('css/styles.css')) }}">

    {{-- White-label: CSS custom properties + custom CSS --}}
    @if(isset($branding) && $branding)
    <style @nonce>
        :root {
            @if($branding->primary_color)   --color-primary:   {{ $branding->primary_color }};   @endif
            @if($branding->secondary_color) --color-secondary: {{ $branding->secondary_color }}; @endif
            @if($branding->accent_color)    --color-accent:    {{ $branding->accent_color }};    @endif
            @if($branding->text_color)      --color-text:      {{ $branding->text_color }};      @endif
            @if($branding->bg_color)        --color-bg:        {{ $branding->bg_color }};        @endif
            @if($_wlFont)                   --font-family:     '{{ $_wlFont }}', sans-serif;      @endif
        }
        @if($_wlFont)
        body, .form-control, .btn, .nav-label, .card {
            font-family: '{{ $_wlFont }}', sans-serif;
        }
        @endif
        @if($branding->primary_color)
        .sidebar { background: {{ $branding->primary_color }} !important; }
        .btn-primary { background: {{ $branding->primary_color }} !important; border-color: {{ $branding->primary_color }} !important; }
        @endif
        @if($branding->accent_color)
        .btn-success, .badge.bg-success { background: {{ $branding->accent_color }} !important; border-color: {{ $branding->accent_color }} !important; }
        @endif
        @if($branding->custom_css)
        {!! $branding->custom_css !!}
        @endif
    </style>
    @endif

    {{-- Apply saved theme before first paint to avoid flash --}}
    <script @nonce>
        (function() {
            var t = localStorage.getItem('theme');
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    @stack('styles')
</head>

<body>

    {{-- Sidebar --}}
    <nav id="sidebar">
        <div class="sidebar-brand">
            @if(isset($branding) && $branding?->logo_path)
                <img src="{{ Storage::url($branding->logo_path) }}"
                     alt="{{ $branding->app_name ?? config('app.name') }}"
                     class="me-2"
                     style="max-height:36px;max-width:110px;object-fit:contain;filter:brightness(0) invert(1)">
            @else
                <div class="brand-logo me-2">
                    <i class="fas fa-crosshairs"></i>
                </div>
            @endif
            <span class="brand-text">{{ $branding?->app_name ?? __('pos.app_name') }}</span>
            {{-- Compact toggle: desktop only --}}
            <button class="btn btn-sm d-none d-md-flex ms-auto sidebar-compact-btn" data-fn="toggleSidebarCompact"
                title="{{ app()->getLocale() === 'ar' ? 'تصغير القائمة' : 'Compact menu' }}">
                <i class="fas fa-chevron-left sidebar-compact-icon"></i>
            </button>
            {{-- Close button: mobile only --}}
            <button class="btn btn-sm d-md-none ms-auto sidebar-close-btn" data-fn="toggleSidebar">
                <i class="fas fa-xmark fa-lg"></i>
            </button>
        </div>
@php $isMasterTenant = config('tenancy.master_tenant') && tenancy()->tenant?->id === config('tenancy.master_tenant'); @endphp
<div class="sidebar-menu mt-2">
    @if($isMasterTenant)
        {{-- ═══════════════ MASTER TENANT PANEL ═══════════════ --}}
        <a href="{{ route('admin.cpanel') }}" class="{{ request()->routeIs('admin.cpanel') ? 'active' : '' }}">
            <i class="fas fa-gauge-high"></i><span class="nav-label"> {{ __('pos.dashboard') }}</span>
        </a>
        <a href="{{ route('admin.tenants') }}" class="{{ request()->routeIs('admin.tenants') ? 'active' : '' }}">
            <i class="fas fa-building-columns"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الاشتراكات' : 'Subscriptions' }}</span>
        </a>
        <a href="{{ route('admin.plans') }}" class="{{ request()->routeIs('admin.plans') ? 'active' : '' }}">
            <i class="fas fa-tag"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الخطط والأسعار' : 'Plans & Pricing' }}</span>
        </a>
        <a href="{{ route('admin.payment-accounts.page') }}" class="{{ request()->routeIs('admin.payment-accounts.page') ? 'active' : '' }}">
            <i class="fas fa-wallet"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'وسائل الدفع' : 'Payment Methods' }}</span>
        </a>
        <a href="{{ route('roles') }}" class="{{ request()->routeIs('roles') ? 'active' : '' }}">
            <i class="fas fa-shield-halved"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الأدوار والصلاحيات' : 'Roles & Permissions' }}</span>
        </a>
        <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? 'active' : '' }}">
            <i class="fas fa-gear"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'إعدادات النظام' : 'System Settings' }}</span>
        </a>

    @else
        {{-- ═══════════════ TENANT NAVIGATION ═══════════════ --}}

        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard', 'home') ? 'active' : '' }}">
            <i class="fas fa-gauge-high"></i><span class="nav-label"> {{ __('pos.dashboard') }}</span>
        </a>

        {{-- ──────────── OPERATIONS ──────────── --}}
        @permission('view_pos')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ app()->getLocale()==='ar' ? 'العمليات' : 'Operations' }}</div>
        <a href="{{ route('pos') }}" class="{{ request()->routeIs('pos') ? 'active' : '' }}">
            <i class="fas fa-cash-register"></i><span class="nav-label"> {{ __('pos.pos') }}</span>
        </a>
        @endpermission

        @permission('view_returns')
        <a href="{{ route('returns') }}" class="{{ request()->routeIs('returns') ? 'active' : '' }}">
            <i class="fas fa-rotate-left"></i><span class="nav-label"> {{ __('pos.returns') }}</span>
        </a>
        @endpermission

        @permission('view_kitchen')
        @planFeature('kitchen_display')
        <a href="{{ route('kitchen') }}" class="{{ request()->routeIs('kitchen*') ? 'active' : '' }}">
            <i class="fas fa-utensils"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'شاشة المطبخ' : 'Kitchen Display' }}</span>
        </a>
        @endplanFeature
        @endpermission

        @permission('view_qr_orders')
        @planFeature('qr_ordering')
        <a href="{{ route('qr-tables') }}" class="{{ request()->routeIs('qr-tables*') ? 'active' : '' }}">
            <i class="fas fa-qrcode"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'طلبات QR' : 'QR Ordering' }}</span>
        </a>
        @endplanFeature
        @endpermission

        {{-- Phase 11: Kiosk --}}
        @permission('view_kiosk')
        @planFeature('kiosk')
        <a href="{{ route('kiosk') }}" class="{{ request()->routeIs('kiosk*') ? 'active' : '' }}" target="_blank">
            <i class="fas fa-tablet-screen-button"></i><span class="nav-label"> {{ __('pos.kiosk_mode') }}</span>
        </a>
        @endplanFeature
        @endpermission

        {{-- Phase 2: Shift Management --}}
        @planFeature('shift_management')
        <a href="{{ route('shifts.my') }}" class="{{ request()->routeIs('shifts.my') ? 'active' : '' }}">
            <i class="fas fa-clock"></i><span class="nav-label"> {{ __('pos.my_shift') }}</span>
        </a>
        @permission('view_shifts')
        <a href="{{ route('shifts.index') }}" class="{{ request()->routeIs('shifts.index') ? 'active' : '' }}">
            <i class="fas fa-user-clock"></i><span class="nav-label"> {{ __('pos.shift_management') }}</span>
        </a>
        @endpermission
        @endplanFeature

        {{-- ──────────── INVENTORY & SUPPLY ──────────── --}}
        @permission('view_warehouse')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ app()->getLocale()==='ar' ? 'المخزون والتوريد' : 'Inventory & Supply' }}</div>
        <a href="{{ route('warehouse') }}" class="{{ request()->routeIs('warehouse') ? 'active' : '' }}">
            <i class="fas fa-boxes-stacked"></i><span class="nav-label"> {{ __('pos.warehouse') }}</span>
        </a>
        @planFeature('multi_warehouse')
        <a href="{{ route('warehouses') }}" class="{{ request()->routeIs('warehouses') ? 'active' : '' }}">
            <i class="fas fa-warehouse"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'المستودعات' : 'Warehouses' }}</span>
        </a>
        @endplanFeature
        @planFeature('waste_tracking')
        <a href="{{ route('waste') }}" class="{{ request()->routeIs('waste') ? 'active' : '' }}">
            <i class="fas fa-trash-alt"></i><span class="nav-label"> {{ __('pos.waste_recording') }}</span>
        </a>
        @endplanFeature
        @endpermission

        @permission('view_suppliers')
        <a href="{{ route('suppliers') }}" class="{{ request()->routeIs('suppliers') ? 'active' : '' }}">
            <i class="fas fa-truck-fast"></i><span class="nav-label"> {{ __('pos.suppliers') }}</span>
        </a>
        @endpermission

        @permission('view_purchase_orders')
        <a href="{{ route('purchase-orders') }}" class="{{ request()->routeIs('purchase-orders') ? 'active' : '' }}">
            <i class="fas fa-file-invoice-dollar"></i><span class="nav-label"> {{ __('pos.purchase_orders') }}</span>
        </a>
        <a href="{{ route('purchase-returns') }}" class="{{ request()->routeIs('purchase-returns') ? 'active' : '' }}">
            <i class="fas fa-arrow-rotate-left"></i><span class="nav-label"> {{ __('pos.purchase_returns') }}</span>
        </a>
        @endpermission

        @permission('view_supplier_payments')
        <a href="{{ route('supplier-payments') }}" class="{{ request()->routeIs('supplier-payments') ? 'active' : '' }}">
            <i class="fas fa-money-bill-transfer"></i><span class="nav-label"> {{ __('pos.supplier_payments') }}</span>
        </a>
        <a href="{{ route('supplier-accounts') }}" class="{{ request()->routeIs('supplier-accounts') ? 'active' : '' }}">
            <i class="fas fa-scale-balanced"></i><span class="nav-label"> {{ __('pos.supplier_accounts') }}</span>
        </a>
        @endpermission

        {{-- ──────────── CUSTOMERS & MARKETING ──────────── --}}
        @permission('view_warehouse')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ app()->getLocale()==='ar' ? 'العملاء والتسويق' : 'Customers & Marketing' }}</div>
        <a href="{{ route('customers') }}" class="{{ request()->routeIs('customers') ? 'active' : '' }}">
            <i class="fas fa-users"></i><span class="nav-label"> {{ __('pos.customers') }}</span>
        </a>
        @planFeature('customer_groups')
        <a href="{{ route('customer-groups') }}" class="{{ request()->routeIs('customer-groups') ? 'active' : '' }}">
            <i class="fas fa-people-group"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'مجموعات العملاء' : 'Customer Groups' }}</span>
        </a>
        @endplanFeature
        @planFeature('promotions')
        <a href="{{ route('promotions') }}" class="{{ request()->routeIs('promotions') ? 'active' : '' }}">
            <i class="fas fa-percent"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'العروض الترويجية' : 'Promotions' }}</span>
        </a>
        @endplanFeature
        @planFeature('cashback')
        <a href="{{ route('cashback') }}" class="{{ request()->routeIs('cashback*') ? 'active' : '' }}">
            <i class="fas fa-coins"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الكاش باك' : 'Cashback' }}</span>
        </a>
        @endplanFeature
        @planFeature('crm')
        @permission('view_crm')
        <a href="{{ route('crm') }}" class="{{ request()->routeIs('crm*') ? 'active' : '' }}">
            <i class="fas fa-users-gear"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'إدارة العملاء CRM' : 'CRM' }}</span>
        </a>
        @endpermission
        @endplanFeature
        @endpermission

        {{-- ──────────── FINANCE ──────────── --}}
        @permission('view_pos')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ app()->getLocale()==='ar' ? 'المالية' : 'Finance' }}</div>
        <a href="{{ route('expenses') }}" class="{{ request()->routeIs('expenses') ? 'active' : '' }}">
            <i class="fas fa-receipt"></i><span class="nav-label"> {{ __('pos.expenses') }}</span>
        </a>
        <a href="{{ route('cash-register') }}" class="{{ request()->routeIs('cash-register') ? 'active' : '' }}">
            <i class="fas fa-cash-register"></i><span class="nav-label"> {{ __('pos.cash_register_settlement') }}</span>
        </a>
        @endpermission

        @permission('view_accounting')
        <a href="{{ route('accounting') }}" class="{{ request()->routeIs('accounting') ? 'active' : '' }}">
            <i class="fas fa-book-open"></i><span class="nav-label"> {{ __('pos.accounting') }}</span>
        </a>
        @endpermission

        @permission('view_financial_reports')
        <a href="{{ route('financial-reports') }}" class="{{ request()->routeIs('financial-reports') ? 'active' : '' }}">
            <i class="fas fa-chart-area"></i><span class="nav-label"> {{ __('pos.financial_reports') }}</span>
        </a>
        @endpermission

        {{-- ──────────── REPORTS & ANALYTICS ──────────── --}}
        @permission('view_reports')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ app()->getLocale()==='ar' ? 'التقارير والتحليلات' : 'Reports & Analytics' }}</div>
        <a href="{{ route('reports') }}" class="{{ request()->routeIs('reports') ? 'active' : '' }}">
            <i class="fas fa-chart-column"></i><span class="nav-label"> {{ __('pos.reports') }}</span>
        </a>
        <a href="{{ route('profit-reports') }}" class="{{ request()->routeIs('profit-reports') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i><span class="nav-label"> {{ __('pos.profit_reports') }}</span>
        </a>
        @planFeature('budget_vs_actual')
        <a href="{{ route('reports.budget') }}" class="{{ request()->routeIs('reports.budget') ? 'active' : '' }}">
            <i class="fas fa-scale-unbalanced"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الميزانية مقابل الفعلي' : 'Budget vs Actual' }}</span>
        </a>
        @endplanFeature
        @planFeature('ai_forecasting')
        <a href="{{ route('forecasting') }}" class="{{ request()->routeIs('forecasting*') ? 'active' : '' }}">
            <i class="fas fa-robot"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'التنبؤ بالذكاء الاصطناعي' : 'AI Forecasting' }}</span>
        </a>
        @endplanFeature
        {{-- Franchise Royalties --}}
        @planFeature('franchise')
        <a href="{{ route('franchise.royalties') }}" class="{{ request()->routeIs('franchise*') ? 'active' : '' }}">
            <i class="fas fa-handshake"></i><span class="nav-label"> {{ __('pos.franchise_royalties') }}</span>
        </a>
        @endplanFeature
        @endpermission

        {{-- ──────────── HR MODULE ──────────── --}}
        @planFeature('hr_module')
        @permission('view_hr')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ __('pos.hr_module') }}</div>
        <a href="{{ route('hr.employees') }}" class="{{ request()->routeIs('hr.employees') ? 'active' : '' }}">
            <i class="fas fa-users-gear"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الموظفون' : 'Employees' }}</span>
        </a>
        <a href="{{ route('hr.attendance') }}" class="{{ request()->routeIs('hr.attendance') ? 'active' : '' }}">
            <i class="fas fa-fingerprint"></i><span class="nav-label"> {{ __('pos.attendance') }}</span>
        </a>
        @planFeature('payroll')
        <a href="{{ route('hr.payroll') }}" class="{{ request()->routeIs('hr.payroll') ? 'active' : '' }}">
            <i class="fas fa-money-check-dollar"></i><span class="nav-label"> {{ __('pos.payroll') }}</span>
        </a>
        @endplanFeature
        <a href="{{ route('hr.leaves') }}" class="{{ request()->routeIs('hr.leaves') ? 'active' : '' }}">
            <i class="fas fa-umbrella-beach"></i><span class="nav-label"> {{ __('pos.leaves') }}</span>
        </a>
        @endpermission
        @endplanFeature

        {{-- ──────────── CONFIGURATION ──────────── --}}
        @permission('manage_pricing_rules')
        <hr class="sidebar-divider">
        <div class="sidebar-section-label">{{ app()->getLocale()==='ar' ? 'الإعدادات والتكوين' : 'Configuration' }}</div>
        @planFeature('pricing_rules')
        <a href="{{ route('pricing-rules') }}" class="{{ request()->routeIs('pricing-rules*') ? 'active' : '' }}">
            <i class="fas fa-tags"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'قواعد التسعير' : 'Pricing Rules' }}</span>
        </a>
        @endplanFeature
        @endpermission

        @permission('manage_roles')
        <a href="{{ route('roles') }}" class="{{ request()->routeIs('roles') ? 'active' : '' }}">
            <i class="fas fa-shield-halved"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'الأدوار والصلاحيات' : 'Roles & Permissions' }}</span>
        </a>
        @planFeature('multi_branch')
        <a href="{{ route('branches') }}" class="{{ request()->routeIs('branches') ? 'active' : '' }}">
            <i class="fas fa-sitemap"></i><span class="nav-label"> {{ __('pos.branches') }}</span>
        </a>
        @endplanFeature
        @planFeature('whatsapp')
        <a href="{{ route('whatsapp') }}" class="{{ request()->routeIs('whatsapp') ? 'active' : '' }}">
            <i class="fab fa-whatsapp"></i><span class="nav-label"> {{ __('pos.whatsapp') }}</span>
        </a>
        @endplanFeature
        @endpermission

        {{-- White Label & Multi-Currency --}}
        @planFeature('white_label')
        @permission('manage_white_label')
        <a href="{{ route('white-label') }}" class="{{ request()->routeIs('white-label*') ? 'active' : '' }}">
            <i class="fas fa-palette"></i><span class="nav-label"> {{ __('pos.white_label') }}</span>
        </a>
        @endpermission
        @endplanFeature

        @planFeature('currencies')
        @permission('manage_currencies')
        <a href="{{ route('currencies.index') }}" class="{{ request()->routeIs('currencies*') ? 'active' : '' }}">
            <i class="fas fa-coins"></i><span class="nav-label"> {{ __('pos.currencies') }}</span>
        </a>
        @endpermission
        @endplanFeature

        @planFeature('device_sessions')
        <a href="{{ route('device-sessions') }}" class="{{ request()->routeIs('device-sessions*') ? 'active' : '' }}">
            <i class="fas fa-laptop-mobile"></i><span class="nav-label"> {{ app()->getLocale()==='ar' ? 'جلسات الأجهزة' : 'Device Sessions' }}</span>
        </a>
        @endplanFeature

        @permission('view_settings')
        <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? 'active' : '' }}">
            <i class="fas fa-gear"></i><span class="nav-label"> {{ __('pos.settings') }}</span>
        </a>
        @endpermission

    @endif
</div>
    </nav>

    {{-- Mobile sidebar backdrop --}}
    <div id="sidebar-backdrop" data-fn="toggleSidebar"></div>

    {{-- Impersonation Banner --}}
    @if(session('impersonator_id'))
    <div id="impersonation-banner" class="system-banner system-banner--impersonate">
        <span>
            <i class="fas fa-user-secret me-2"></i>
            {{ app()->getLocale() === 'ar'
                ? 'أنت تتصفح بوصفك: ' . auth()->user()->full_name
                : 'Viewing as: ' . auth()->user()->full_name }}
        </span>
        <button type="button" class="btn btn-sm btn-dark py-0 px-2" id="leaveImpersonationBtn">
            <i class="fas fa-undo me-1"></i>
            {{ app()->getLocale() === 'ar' ? 'العودة لحسابي' : 'Return to my account' }}
        </button>
    </div>
    <style @nonce>#sidebar, #main-content { margin-top: 36px; }</style>
    @endif

    {{-- Subscription Banner (expired / trial ending soon) --}}
    @php
        $__tenant     = tenancy()->tenant;
        $__masterId   = config('tenancy.master_tenant');
        $__isMaster   = $__masterId && $__tenant?->id === $__masterId;
        $__subStatus  = $__tenant?->subscription_status;
        $__trialEnds  = $__tenant?->trial_ends_at;
        $__trialDays  = $__trialEnds ? (int) now()->diffInDays($__trialEnds, false) : null;
        $__showExpiredBanner = !$__isMaster && in_array($__subStatus, ['expired','suspended','cancelled']);
        $__showTrialBanner   = !$__isMaster && $__subStatus === 'trial' && $__trialDays !== null && $__trialDays <= 7 && $__trialDays >= 0;
    @endphp

    @if($__showExpiredBanner)
    <div id="sub-banner-expired" class="system-banner system-banner--expired">
        <span>
            <i class="fas fa-exclamation-circle me-2"></i>
            @if(app()->getLocale() === 'ar')
                {{ $__subStatus === 'suspended' ? 'حسابك موقوف مؤقتاً.' : ($__subStatus === 'cancelled' ? 'اشتراكك ملغى.' : 'انتهى اشتراكك.') }}
                جدّد الآن للاستمرار في استخدام النظام.
            @else
                {{ $__subStatus === 'suspended' ? 'Your account is suspended.' : ($__subStatus === 'cancelled' ? 'Your subscription is cancelled.' : 'Your subscription has expired.') }}
                Renew now to keep using the system.
            @endif
        </span>
        <a href="{{ route('subscribe') }}" class="banner-cta banner-cta--expired">
            <i class="fas fa-redo me-1"></i>
            {{ app()->getLocale() === 'ar' ? 'جدّد الاشتراك' : 'Renew Now' }}
        </a>
    </div>
    <style @nonce>#sidebar, #main-content { margin-top: 38px; }</style>
    @elseif($__showTrialBanner)
    <div id="sub-banner-trial" style="position:fixed;top:0;left:0;right:0;z-index:9998;background:linear-gradient(90deg,#d97706,#b45309);color:#fff;padding:.55rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;font-size:.84rem;font-weight:600;box-shadow:0 2px 12px rgba(217,119,6,.4);">
        <span>
            <i class="fas fa-hourglass-half me-2"></i>
            @if(app()->getLocale() === 'ar')
                تبقّى <strong>{{ $__trialDays }} {{ $__trialDays === 1 ? 'يوم' : 'أيام' }}</strong> على انتهاء فترة التجربة المجانية.
            @else
                <strong>{{ $__trialDays }} {{ $__trialDays === 1 ? 'day' : 'days' }}</strong> left on your free trial.
            @endif
        </span>
        <a href="{{ route('subscribe') }}" style="background:#fff;color:#b45309;padding:.3rem 1rem;border-radius:.5rem;font-weight:800;text-decoration:none;white-space:nowrap;font-size:.82rem;flex-shrink:0">
            <i class="fas fa-tags me-1"></i>
            {{ app()->getLocale() === 'ar' ? 'اشترك الآن' : 'Subscribe Now' }}
        </a>
    </div>
    <style @nonce>#sidebar, #main-content { margin-top: 38px; }</style>
    @endif

    {{-- Main Content --}}
    <div id="main-content">
        {{-- Topbar --}}
        <div id="topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-sm btn-outline-secondary d-md-none" data-fn="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                {{-- <h6 class="mb-0 fw-semibold d-none d-md-block">@yield('page-title', __('pos.dashboard'))</h6> --}}
           
                {{-- Low Stock Notification Bell --}}
                <div class="dropdown" id="stockNotifDropdown">
                    <button class="btn btn-sm btn-outline-secondary position-relative" id="stockBellBtn"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        data-fn="loadStockAlerts" title="{{ app()->getLocale() === 'ar' ? 'تنبيهات المخزون' : 'Stock Alerts' }}">
                        <i class="fas fa-bell"></i>
                        <span id="stockBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none stock-bell-badge"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 shadow stock-alert-dropdown">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom bg-warning bg-opacity-10">
                            <span class="fw-bold small">
                                <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                {{ app()->getLocale() === 'ar' ? 'تنبيهات المخزون' : 'Stock Alerts' }}
                            </span>
                            <a href="{{ route('warehouse') }}" class="btn btn-xs btn-link btn-sm text-decoration-none p-0 small">
                                {{ app()->getLocale() === 'ar' ? 'عرض الكل' : 'View All' }}
                            </a>
                        </div>
                        <div id="stockAlertsList">
                            <div class="text-center py-3 text-muted small">
                                <i class="fas fa-spinner fa-spin me-1"></i>
                                {{ app()->getLocale() === 'ar' ? 'جاري التحميل...' : 'Loading...' }}
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Dark Mode Toggle --}}
                <button id="themeToggleBtn" class="btn btn-sm btn-outline-secondary"
                    title="{{ app()->getLocale() === 'ar' ? 'تبديل المظهر' : 'Toggle theme' }}">
                    <i class="fas fa-sun icon-sun"></i>
                    <i class="fas fa-moon icon-moon"></i>
                </button>

                {{-- Language Toggle --}}
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-globe"></i>
                        {{ app()->getLocale() === 'ar' ? 'العربية' : 'English' }}
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ app()->getLocale() === 'ar' ? 'active' : '' }}"
                                href="{{ route('lang.switch', 'ar') }}">
                                🇪🇬 العربية
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ app()->getLocale() === 'en' ? 'active' : '' }}"
                                href="{{ route('lang.switch', 'en') }}">
                                🇺🇸 English
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- User Menu --}}
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        {{ auth()->user()->full_name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        {{-- Display user role(s) properly --}}
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                @php
                                    $roles = auth()->user()->getRoleNames();
                                @endphp
                                @if($roles->count() > 0)
                                    {{ ucfirst($roles->implode(', ')) }}
                                @else
                                    {{ __('pos.no_role') }}
                                @endif
                            </span>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('device-sessions') }}">
                                <i class="fas fa-shield-halved me-2"></i>{{ app()->getLocale()==='ar' ? 'الأجهزة النشطة' : 'Active Devices' }}
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST">
                                @csrf
                                <button class="dropdown-item text-danger">
                                    <i class="fas fa-sign-out-alt me-2"></i>{{ __('pos.logout') }}
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Page Header --}}
        @hasSection('page-title')
        <div class="page-header">
            <h1 class="page-header-title">@yield('page-title')</h1>
        </div>
        @endif

        {{-- Page Content --}}
        <div class="page-content">
            @yield('content')
        </div>

        {{-- White-label footer --}}
        @php
            $_showFooter = isset($branding)
                ? ($branding?->footer_text || !($branding?->hide_powered_by))
                : true;  // default footer when no branding saved
        @endphp
        @if($_showFooter)
        <div class="text-center text-muted py-2" style="font-size:.78rem;border-top:1px solid rgba(0,0,0,.06);margin-top:.5rem">
            @if(isset($branding) && $branding?->footer_text)
                {{ $branding->footer_text }}
                @if(!($branding?->hide_powered_by)) &nbsp;·&nbsp; @endif
            @endif
            @if(!isset($branding) || !($branding?->hide_powered_by))
                {{ app()->getLocale() === 'ar' ? 'بتقنية' : 'Powered by' }}
                <strong>{{ $branding?->app_name ?? config('app.name') }}</strong>
            @endif
        </div>
        @endif
    </div>

    {{-- Toast Container --}}
    <div class="toast-container" id="toastContainer"></div>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>

    <script @nonce>
        // CSRF token for AJAX - رمز CSRF لطلبات AJAX
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const LOCALE = '{{ app()->getLocale() }}';

        // Helper: Show toast notification (SweetAlert2)
        const _swalToast = Swal.mixin({
            toast: true,
            position: LOCALE === 'ar' ? 'top-start' : 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
        });
        function showToast(message, type = 'success') {
            const iconMap = { success: 'success', danger: 'error', warning: 'warning', info: 'info', error: 'error' };
            _swalToast.fire({ icon: iconMap[type] || 'success', title: message });
        }

        // Helper: Confirm dialog (SweetAlert2) — returns Promise<boolean>
        async function confirmAction({ title, text, confirmText, cancelText, icon = 'warning', confirmColor = '#ef4444' } = {}) {
            const isAr = LOCALE === 'ar';
            const result = await Swal.fire({
                title: title || (isAr ? 'هل أنت متأكد؟' : 'Are you sure?'),
                text: text || '',
                icon,
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#6b7280',
                confirmButtonText: confirmText || (isAr ? 'تأكيد' : 'Confirm'),
                cancelButtonText: cancelText || (isAr ? 'إلغاء' : 'Cancel'),
                reverseButtons: !isAr,
                customClass: { popup: 'swal2-popup' },
            });
            return result.isConfirmed;
        }

        // Helper: Delete confirm shortcut
        async function confirmDelete(message) {
            const isAr = LOCALE === 'ar';
            return confirmAction({
                title: isAr ? 'تأكيد الحذف' : 'Confirm Delete',
                text: message || (isAr ? 'لا يمكن التراجع عن هذا الإجراء.' : 'This action cannot be undone.'),
                confirmText: isAr ? 'نعم، احذف' : 'Yes, delete',
                confirmColor: '#ef4444',
            });
        }

        // Helper: API call - طلب API
        async function apiCall(url, method = 'GET', data = null) {
            const options = {
                method,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN,
                    'Accept': 'application/json',
                }
            };
            if (data) options.body = JSON.stringify(data);
            const res = await fetch(url, options);
            return res.json();
        }

function toggleSidebar() {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar) return;

    const isOpen = sidebar.classList.contains('show');

    if (isOpen) {
        sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('show');
        if (backdrop) backdrop.classList.add('show');
        // Prevent body scroll while sidebar is open on mobile
        if (window.innerWidth <= 768) document.body.style.overflow = 'hidden';
    }
}

// Close sidebar on resize to desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const sidebar  = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar)  sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
        document.body.style.overflow = '';
    }
});

// ── Compact sidebar (icon-only mode) ─────────────────────────────────────────
const _isRTL = () => document.body.classList.contains('rtl');

function _applyCompact(compact) {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    if (compact) {
        sidebar.classList.add('compact');
        document.body.classList.add('sidebar-compact');

        // Init a Bootstrap tooltip on every nav link (appended to <body> → never clipped)
        sidebar.querySelectorAll('.sidebar-menu a').forEach(function(link) {
            const label = link.querySelector('.nav-label');
            if (!label) return;
            const text = label.textContent.trim();
            if (!text) return;
            // Avoid double-init
            if (bootstrap.Tooltip.getInstance(link)) return;
            new bootstrap.Tooltip(link, {
                title:       text,
                placement:   _isRTL() ? 'left' : 'right',
                trigger:     'hover',
                customClass: 'sidebar-nav-tooltip',
                boundary:    'viewport',
            });
        });
    } else {
        sidebar.classList.remove('compact');
        document.body.classList.remove('sidebar-compact');

        // Destroy all nav tooltips so they don't fire in expanded mode
        sidebar.querySelectorAll('.sidebar-menu a').forEach(function(link) {
            const tip = bootstrap.Tooltip.getInstance(link);
            if (tip) tip.dispose();
        });
    }
}

function toggleSidebarCompact() {
    const isCompact = document.getElementById('sidebar').classList.contains('compact');
    const next = !isCompact;
    localStorage.setItem('sidebarCompact', next ? '1' : '0');
    _applyCompact(next);
}

// Restore compact state on every page load (desktop only)
(function() {
    if (window.innerWidth > 768 && localStorage.getItem('sidebarCompact') === '1') {
        _applyCompact(true);
    }
})();
        // Format currency - تنسيق العملة
        function formatCurrency(amount) {
            return new Intl.NumberFormat(LOCALE === 'ar' ? 'ar-EG' : 'en-US', {
                style: 'currency',
                currency: 'EGP',
                minimumFractionDigits: 2
            }).format(amount || 0);
        }

        // Format date - تنسيق التاريخ
        function formatDate(date) {
            return new Date(date).toLocaleDateString(LOCALE === 'ar' ? 'ar-EG' : 'en-US');
        }

        // Add RTL/LTR class to body based on locale
        const locale = '{{ app()->getLocale() }}';
        if (locale === 'ar') {
            document.body.classList.add('rtl');
        } else {
            document.body.classList.add('ltr');
        }

        // ── Theme toggle ──────────────────────────────────────────────────────────
        (function initTheme() {
            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
            }

            document.getElementById('themeToggleBtn').addEventListener('click', function() {
                const current = document.documentElement.getAttribute('data-theme') || 'light';
                applyTheme(current === 'dark' ? 'light' : 'dark');
            });

            // Sync with OS preference changes (e.g., user switches system theme)
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                if (!localStorage.getItem('theme')) {
                    applyTheme(e.matches ? 'dark' : 'light');
                }
            });
        })();

        // ── CSP-safe global dispatchers (replace all inline onclick/onchange/oninput) ──
        // data-fn="fnName" [data-args='[arg1,arg2]'] → calls window.fnName(arg1, arg2, element)
        // data-on-change="fnName" → calls window.fnName(element) on change
        // data-on-input="fnName"  → calls window.fnName(element) on input
        document.addEventListener('click', function(e) {
            const el = e.target.closest('[data-fn]');
            if (!el) return;
            const fn = el.dataset.fn;
            if (typeof window[fn] !== 'function') return;
            let args = [];
            if (el.dataset.args !== undefined) {
                try {
                    const parsed = JSON.parse(el.dataset.args);
                    args = Array.isArray(parsed) ? parsed : [parsed];
                } catch (_) { args = [el.dataset.args]; }
            }
            window[fn](...args, el);
        });
        document.addEventListener('change', function(e) {
            const el = e.target.closest('[data-on-change]');
            if (!el) return;
            const fn = el.dataset.onChange;
            if (typeof window[fn] === 'function') window[fn](el);
        });
        document.addEventListener('input', function(e) {
            const el = e.target.closest('[data-on-input]');
            if (!el) return;
            const fn = el.dataset.onInput;
            if (typeof window[fn] === 'function') window[fn](el);
        });
    </script>

    <script @nonce>
    // ── Leave impersonation (fresh CSRF at click time, avoids stale-page 419) ──
    (function () {
        const btn = document.getElementById('leaveImpersonationBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("impersonate.leave") }}';
            form.innerHTML = '<input type="hidden" name="_token" value="' +
                document.querySelector('meta[name="csrf-token"]').getAttribute('content') + '">';
            document.body.appendChild(form);
            form.submit();
        });
    })();
    // ── Stock Alert Notification Bell ──────────────────────────────────
    (function initStockBell() {
        const isAr = LOCALE === 'ar';

        async function fetchAlerts() {
            try {
                const res = await fetch('{{ route("dashboard.low-stock") }}', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }
                });
                return await res.json();
            } catch (e) { return null; }
        }

        function renderAlerts(data) {
            const badge = document.getElementById('stockBadge');
            const list  = document.getElementById('stockAlertsList');
            if (!data) {
                list.innerHTML = `<div class="text-center py-3 text-danger small">${isAr ? 'فشل تحميل البيانات' : 'Failed to load'}</div>`;
                return;
            }

            const total = data.total_alerts;
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }

            if (total === 0) {
                list.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
                        <span class="text-muted small">${isAr ? 'المخزون بخير، لا توجد تنبيهات' : 'All stock levels are fine'}</span>
                    </div>`;
                return;
            }

            let html = '';

            if (data.out_of_stock && data.out_of_stock.length > 0) {
                html += `<div class="px-3 pt-2 pb-1">
                    <span class="badge bg-danger mb-1">${isAr ? 'نفد المخزون' : 'Out of Stock'}</span>
                </div>`;
                data.out_of_stock.forEach(p => {
                    html += `<a href="{{ route('warehouse') }}" class="dropdown-item d-flex align-items-center gap-2 py-2 border-bottom text-decoration-none">
                        <span class="flex-shrink-0 text-danger"><i class="fas fa-times-circle"></i></span>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small text-truncate">${p.name}</div>
                            <div class="text-muted stock-alert-meta">
                                ${isAr ? 'الكمية: ' : 'Qty: '}<strong class="text-danger">0</strong>
                                ${p.category ? ' &bull; ' + p.category : ''}
                            </div>
                        </div>
                        <span class="badge bg-danger bg-opacity-15 text-white border border-danger stock-alert-badge">${isAr ? 'نفذ' : 'Empty'}</span>
                    </a>`;
                });
            }

            if (data.low_stock && data.low_stock.length > 0) {
                html += `<div class="px-3 pt-2 pb-1">
                    <span class="badge bg-warning text-dark mb-1">${isAr ? 'مخزون منخفض' : 'Low Stock'}</span>
                </div>`;
                data.low_stock.forEach(p => {
                    html += `<a href="{{ route('warehouse') }}" class="dropdown-item d-flex align-items-center gap-2 py-2 border-bottom text-decoration-none">
                        <span class="flex-shrink-0 text-warning"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small text-truncate">${p.name}</div>
                            <div class="text-muted stock-alert-meta">
                                ${isAr ? 'الكمية: ' : 'Qty: '}<strong class="text-warning">${p.quantity}</strong>
                                ${isAr ? ' / الحد الأدنى: ' : ' / Min: '}<strong>${p.min_stock}</strong>
                                ${p.category ? ' &bull; ' + p.category : ''}
                            </div>
                        </div>
                        <span class="badge bg-warning bg-opacity-15 text-white border border-warning stock-alert-badge">${isAr ? 'منخفض' : 'Low'}</span>
                    </a>`;
                });
            }

            list.innerHTML = html;
        }

        window.loadStockAlerts = async function() {
            const list = document.getElementById('stockAlertsList');
            list.innerHTML = `<div class="text-center py-3 text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>${isAr ? 'جاري التحميل...' : 'Loading...'}</div>`;
            const data = await fetchAlerts();
            renderAlerts(data);
        };

        // Auto-load badge count on page load
        fetchAlerts().then(data => {
            if (!data) return;
            const badge = document.getElementById('stockBadge');
            const total = data.total_alerts;
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : total;
                badge.classList.remove('d-none');
                // Show a toast on page load if there are alerts
                if (total > 0) {
                    const msg = isAr
                        ? `⚠️ تنبيه: ${total} منتج ${total === 1 ? 'قرب على النفاذ أو نفذ' : 'منتجات قربت على النفاذ أو نفذت'} من المخزون`
                        : `⚠️ Alert: ${total} product${total > 1 ? 's' : ''} with low or no stock`;
                    setTimeout(() => showToast(msg, 'warning'), 1000);
                }
            }
        });
    })();

    // ── Sidebar: scroll active item into view on every page load ─────────────
    (function () {
        const menu   = document.querySelector('.sidebar-menu');
        const active = menu && menu.querySelector('a.active');
        if (!menu || !active) return;
        // Centre the active link inside the scrollable menu — no animation so
        // there is no visible "jump" after the page has painted.
        const target = active.offsetTop - (menu.clientHeight / 2) + (active.clientHeight / 2);
        menu.scrollTop = Math.max(0, target);
    })();
    // ────────────────────────────────────────────────────────────────────
    </script>

    @stack('scripts')
</body>

</html>