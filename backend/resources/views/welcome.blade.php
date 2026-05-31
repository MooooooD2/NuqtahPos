@php
    $isAr = session('locale', config('app.locale', 'ar')) === 'ar';

    // ── Dynamic theme from white-label branding (passed from route) ────────
    // Falls back to hard-coded brand colours when branding is null.
    $_brand        = $branding?->primary_color   ?? '#12244E';
    $_brandDark    = $branding?->secondary_color ?? '#0D1B3A';
    $_accent       = $branding?->accent_color    ?? '#00B04E';
    $_textColor    = $branding?->text_color      ?? null;
    $_bgColor      = $branding?->bg_color        ?? null;
    $_appName      = $branding?->app_name        ?? 'NuqtahPOS';
    $_hidePowered  = (bool)($branding?->hide_powered_by ?? false);
    $_footerText   = $branding?->footer_text     ?? null;
    $_supportEmail = $branding?->support_email   ?? null;
    $_website      = $branding?->website_url     ?? null;

    // Choose font: brand font > locale default
    $_wlFont   = $branding?->font_family ?? ($isAr ? 'Cairo' : 'Inter');
    $_fontMap  = [
        'Cairo'                => 'https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap',
        'Inter'                => 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700;900&display=swap',
        'Tajawal'              => 'https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap',
        'IBM Plex Sans Arabic' => 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;700&display=swap',
        'Roboto'               => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
        'Poppins'              => 'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap',
    ];

    // Derive a lighter version of the accent for backgrounds (pure CSS fallback)
    $_accentLight = $branding?->accent_color  ? $_accent . '22' : '#E0F7EC';
    $_brandLight  = $branding?->primary_color ? $_brand  . '18' : '#E8F0FC';
@endphp
<!DOCTYPE html>
<html lang="{{ $isAr ? 'ar' : 'en' }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $_appName }}{{ $branding ? '' : ' — ' . ($isAr ? 'نظام نقطة البيع السحابي' : 'Cloud POS System') }}</title>
    <meta name="description" content="{{ $isAr
        ? 'نظام نقطة بيع سحابي متكامل للشركات الصغيرة والمتوسطة. إدارة المبيعات، المخزون، والحسابات في مكان واحد.'
        : 'Full-featured cloud POS system for small and medium businesses. Manage sales, inventory, and accounting in one place.' }}">

    {{-- Favicon --}}
    @if($branding?->favicon_path)
        <link rel="icon" href="{{ Storage::url($branding->favicon_path) }}">
    @else
        <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text x='50' y='50' text-anchor='middle' dominant-baseline='middle' font-size='80'>🏪</text></svg>">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Load brand font (or both defaults when no branding) --}}
    @if(isset($_fontMap[$_wlFont]))
        <link href="{{ $_fontMap[$_wlFont] }}" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;700;900&display=swap" rel="stylesheet">
    @endif

    @if($isAr)
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.rtl.min.css">
    @else
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <style nonce="{{ app('csp-nonce') }}">
        :root {
            --brand:        {{ $_brand }};
            --brand-dark:   {{ $_brandDark }};
            --brand-light:  {{ $_brandLight }};
            --accent:       {{ $_accent }};
            --accent-light: {{ $_accentLight }};
            --success:      #059669;
            --dark:         #0f172a;
            --gray:         #64748b;
            @if($_textColor) --color-text: {{ $_textColor }}; @endif
            @if($_bgColor)   --color-bg:   {{ $_bgColor }};   @endif
        }

        * {
            font-family: '{{ $_wlFont }}', {{ $isAr ? 'Cairo,' : '' }} sans-serif;
            box-sizing: border-box;
        }

        html { scroll-behavior: smooth; overflow-x: hidden; }
        body { overflow-x: hidden; background: #fff; }


        /* ── Navbar ── */
        .navbar {
            background: rgba(255,255,255,.92); backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0,0,0,.06); padding: .875rem 0; transition: all .3s;
        }
        .navbar.scrolled { box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .navbar-brand {
            font-weight: 900; font-size: 1.45rem; color: var(--brand) !important;
            letter-spacing: -.02em; display: flex; align-items: center; gap: .55rem;
        }
        .navbar-brand .nb-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--brand), var(--accent));
            border-radius: .6rem; display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: .95rem; flex-shrink: 0;
        }
        .navbar-brand .nb-dot { color: var(--accent); }
        .nav-link {
            font-weight: 600; color: #374151 !important; padding: .4rem .75rem !important;
            border-radius: .5rem; transition: all .2s;
        }
        .nav-link:hover { color: var(--brand) !important; background: var(--brand-light); }
        .btn-nav-login {
            border: 1.5px solid #d1d5db; color: #374151; border-radius: .625rem;
            padding: .45rem 1.25rem; font-weight: 600; transition: all .2s;
        }
        .btn-nav-login:hover { border-color: var(--brand); color: var(--brand); }
        .btn-nav-cta {
            background: linear-gradient(135deg, var(--brand), var(--accent));
            color: #fff !important; border-radius: .625rem; padding: .45rem 1.25rem;
            font-weight: 700; border: none; transition: all .2s;
        }
        .btn-nav-cta:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(18,36,78,.35); }

        /* ── Lang switcher ── */
        .btn-lang {
            border: 1.5px solid var(--brand); color: var(--brand); border-radius: .625rem;
            padding: .4rem .9rem; font-weight: 700; font-size: .82rem; transition: all .2s;
            background: transparent; display: inline-flex; align-items: center; gap: .35rem;
        }
        .btn-lang:hover { background: var(--brand); color: #fff; }

        /* ── Hero ── */
        .hero {
            background: linear-gradient(145deg, var(--brand-dark) 0%, var(--brand) 45%, color-mix(in srgb, var(--accent) 20%, var(--brand-dark)) 75%, var(--brand-dark) 100%);
            min-height: 100vh; display: flex; align-items: center;
            position: relative; overflow: hidden; padding: 7rem 0 5rem;
        }
        .hero-grid {
            position: absolute; inset: 0;
            background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .hero-glow-1 {
            position: absolute; width: 600px; height: 600px;
            background: radial-gradient(circle, color-mix(in srgb, var(--accent) 22%, transparent) 0%, transparent 70%);
            top: -100px; right: -100px; pointer-events: none;
        }
        .hero-glow-2 {
            position: absolute; width: 500px; height: 500px;
            background: radial-gradient(circle, color-mix(in srgb, var(--brand) 55%, transparent) 0%, transparent 70%);
            bottom: -100px; left: -100px; pointer-events: none;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
            border-radius: 2rem; padding: .4rem 1.1rem; font-size: .85rem;
            color: rgba(255,255,255,.9); margin-bottom: 1.5rem; backdrop-filter: blur(10px);
        }
        .hero-badge-dot {
            width: 8px; height: 8px; background: #4ade80; border-radius: 50%;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot {
            0%,100% { box-shadow: 0 0 0 0 rgba(74,222,128,.4) }
            50%      { box-shadow: 0 0 0 6px rgba(74,222,128,0) }
        }
        .hero-title { font-size: clamp(2.6rem,5.5vw,4.2rem); font-weight: 900; line-height: 1.15; letter-spacing: -.02em; }
        .gradient-text {
            background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 80%, #fff), var(--accent), #22d3ee);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero-sub { font-size: 1.15rem; color: rgba(255,255,255,.72); line-height: 1.7; max-width: 500px; }
        .hero-trust { font-size: .82rem; color: rgba(255,255,255,.55); }
        .btn-hero-primary {
            background: #fff; color: var(--brand); font-weight: 800; font-size: 1.05rem;
            padding: .85rem 2.25rem; border-radius: .75rem; border: none; transition: all .25s;
            box-shadow: 0 8px 30px rgba(0,0,0,.2);
        }
        .btn-hero-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,.3); color: var(--brand-dark); }
        .btn-hero-secondary {
            background: rgba(255,255,255,.08); color: #fff; font-weight: 700; font-size: 1.05rem;
            padding: .85rem 2rem; border-radius: .75rem; border: 1px solid rgba(255,255,255,.2);
            transition: all .25s; backdrop-filter: blur(10px);
        }
        .btn-hero-secondary:hover { background: rgba(255,255,255,.15); color: #fff; transform: translateY(-2px); }

        /* ── Hero Stats ── */
        .hero-stat-bar {
            background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
            border-radius: 1rem; padding: 1.25rem 2rem; backdrop-filter: blur(16px);
            display: flex; gap: 2.5rem; flex-wrap: wrap; margin-top: 3rem;
        }
        .hero-stat { text-align: center; }
        .hero-stat-num {
            font-size: 1.9rem; font-weight: 900;
            background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 70%, #fff), var(--accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            line-height: 1.1;
        }
        .hero-stat-lbl { font-size: .78rem; color: rgba(255,255,255,.6); margin-top: .2rem; }

        /* ── POS Mockup ── */
        .pos-mockup {
            background: #1e293b; border-radius: 1.25rem; border: 1px solid rgba(255,255,255,.1);
            box-shadow: 0 40px 80px rgba(0,0,0,.5); overflow: hidden;
        }
        .pos-topbar {
            background: #0f172a; padding: .75rem 1.25rem;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }
        .pos-dots span { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-left: .35rem; }
        .pos-dot-r { background: #ef4444; }
        .pos-dot-y { background: #f59e0b; }
        .pos-dot-g { background: #22c55e; }
        .pos-title-bar { font-size: .75rem; color: rgba(255,255,255,.5); font-weight: 600; }
        .pos-body { display: flex; height: 320px; }
        .pos-left { flex: 1; padding: 1rem; border-left: 1px solid rgba(255,255,255,.06); }
        .pos-right { width: 200px; padding: 1rem; background: rgba(0,0,0,.2); }
        .pos-product-row {
            display: flex; align-items: center; gap: .6rem; padding: .5rem;
            background: rgba(255,255,255,.04); border-radius: .5rem; margin-bottom: .5rem;
        }
        .pos-product-img {
            width: 36px; height: 36px; border-radius: .375rem;
            background: linear-gradient(135deg, var(--brand), var(--accent));
            display: flex; align-items: center; justify-content: center; font-size: .9rem; flex-shrink: 0;
        }
        .pos-product-name { font-size: .72rem; color: rgba(255,255,255,.8); font-weight: 600; }
        .pos-product-price { font-size: .68rem; color: rgba(255,255,255,.45); }
        .pos-product-qty {
            font-size: .7rem; color: #60a5fa; background: rgba(96,165,250,.1);
            border-radius: .25rem; padding: .1rem .4rem; margin-right: auto;
        }
        .pos-total-box {
            background: rgba(0,176,78,.15); border: 1px solid rgba(0,176,78,.3);
            border-radius: .75rem; padding: .75rem; text-align: center; margin-bottom: .75rem;
        }
        .pos-total-label { font-size: .65rem; color: rgba(255,255,255,.5); }
        .pos-total-num { font-size: 1.4rem; font-weight: 900; color: #4ade80; }
        .pos-pay-btn {
            width: 100%; padding: .55rem; border-radius: .5rem;
            background: linear-gradient(135deg, var(--brand), var(--accent));
            border: none; color: #fff; font-size: .75rem; font-weight: 700; cursor: pointer; margin-bottom: .4rem;
        }
        .pos-pay-cash {
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: rgba(255,255,255,.6);
        }
        .pos-search {
            background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
            border-radius: .5rem; padding: .4rem .75rem; font-size: .7rem; color: rgba(255,255,255,.6);
            width: 100%; margin-bottom: .75rem; display: flex; align-items: center; gap: .4rem;
        }
        .pos-chart-bar { display: flex; align-items: flex-end; gap: 4px; height: 60px; margin-top: auto; padding-top: .5rem; border-top: 1px solid rgba(255,255,255,.06); }
        .pos-bar { flex: 1; border-radius: 3px 3px 0 0; background: linear-gradient(to top, var(--brand), var(--accent)); opacity: .7; }
        .pos-badge {
            position: absolute; top: -12px; left: -12px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff; border-radius: .75rem; padding: .5rem .9rem;
            font-size: .78rem; font-weight: 700; box-shadow: 0 8px 24px rgba(34,197,94,.4); white-space: nowrap;
        }
        .pos-badge-2 {
            position: absolute; bottom: -12px; right: -12px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff; border-radius: .75rem; padding: .5rem .9rem;
            font-size: .78rem; font-weight: 700; box-shadow: 0 8px 24px rgba(245,158,11,.4); white-space: nowrap;
        }

        /* ── Trusted by ── */
        .trusted-bar { background: #f8fafc; border-top: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; padding: 1.5rem 0; }
        .trusted-label { font-size: .82rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; white-space: nowrap; }
        .trust-logos { display: flex; align-items: center; gap: 2.5rem; flex-wrap: wrap; justify-content: center; }
        .trust-logo { font-size: 1.1rem; font-weight: 800; color: #cbd5e1; letter-spacing: -.03em; transition: color .2s; }
        .trust-logo:hover { color: #94a3b8; }
        .trust-logo i { margin-left: .35rem; }

        /* ── Section ── */
        .section-pill {
            display: inline-block;
            background: linear-gradient(135deg, var(--brand-light), var(--accent-light));
            color: var(--brand); font-weight: 700; font-size: .78rem;
            letter-spacing: .07em; text-transform: uppercase;
            border-radius: 2rem; padding: .35rem 1rem; margin-bottom: 1rem;
        }
        .section-title { font-size: clamp(1.8rem,3.5vw,2.6rem); font-weight: 900; color: var(--dark); letter-spacing: -.025em; line-height: 1.2; }
        .section-sub { color: var(--gray); font-size: 1.05rem; max-width: 520px; margin: .75rem auto 0; line-height: 1.7; }

        /* ── Features ── */
        .feat-card {
            background: #fff; border: 1.5px solid #e5e7eb; border-radius: 1.25rem;
            padding: 2rem; height: 100%; transition: all .3s; position: relative; overflow: hidden;
        }
        .feat-card::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, var(--brand-light), var(--accent-light));
            opacity: 0; transition: opacity .3s;
        }
        .feat-card:hover { border-color: var(--brand); transform: translateY(-5px); box-shadow: 0 20px 50px rgba(18,36,78,.1); }
        .feat-card:hover::before { opacity: .4; }
        .feat-icon { width: 54px; height: 54px; border-radius: .875rem; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin-bottom: 1.1rem; position: relative; }
        .feat-card h5 { font-weight: 800; font-size: 1.05rem; color: var(--dark); margin-bottom: .5rem; position: relative; }
        .feat-card p { font-size: .875rem; color: var(--gray); line-height: 1.65; margin: 0; position: relative; }

        /* ── How it works ── */
        .step-num {
            width: 52px; height: 52px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand), var(--accent));
            color: #fff; font-size: 1.25rem; font-weight: 900;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(18,36,78,.3);
        }
        .step-connector { width: 2px; height: 48px; background: linear-gradient(to bottom, var(--brand), var(--accent)); margin: .5rem auto; opacity: .25; }

        /* ── Pricing ── */
        .price-card { border: 2px solid #e5e7eb; border-radius: 1.5rem; padding: 2.5rem; height: 100%; position: relative; transition: all .3s; background: #fff; }
        .price-card:hover { transform: translateY(-6px); box-shadow: 0 24px 60px rgba(0,0,0,.1); }
        .price-card.best { border-color: var(--brand); background: linear-gradient(160deg, var(--brand-light), #fff); box-shadow: 0 20px 60px color-mix(in srgb, var(--brand) 15%, transparent); }
        .price-best-tag {
            position: absolute; top: -16px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(135deg, var(--brand), var(--accent));
            color: #fff; padding: .35rem 1.5rem; border-radius: 2rem;
            font-size: .78rem; font-weight: 800; white-space: nowrap; box-shadow: 0 4px 16px rgba(18,36,78,.4);
        }
        .price-amount { font-size: 3.2rem; font-weight: 900; letter-spacing: -.04em; line-height: 1; }
        .price-period { font-size: .9rem; color: var(--gray); }
        .price-feature-list { list-style: none; padding: 0; margin: 0; }
        .price-feature-list li { padding: .5rem 0; display: flex; align-items: flex-start; gap: .6rem; font-size: .88rem; color: #374151; border-bottom: 1px solid #f1f5f9; }
        .price-feature-list li:last-child { border-bottom: none; }
        .pfl-check { color: var(--success); flex-shrink: 0; margin-top: .15rem; }
        .pfl-cross { color: #d1d5db; flex-shrink: 0; margin-top: .15rem; }
        .pfl-cross + span { color: #94a3b8; }

        /* ── Testimonials ── */
        .testi-card { background: #fff; border: 1.5px solid #e5e7eb; border-radius: 1.25rem; padding: 2rem; height: 100%; transition: all .3s; }
        .testi-card:hover { border-color: var(--brand); transform: translateY(-4px); box-shadow: 0 16px 40px rgba(18,36,78,.08); }
        .testi-quote { font-size: 2.5rem; color: var(--brand); line-height: 1; opacity: .3; font-family: Georgia, serif; }
        .testi-text { font-size: .925rem; color: #374151; line-height: 1.75; }
        .testi-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; color: #fff; flex-shrink: 0; }

        /* ── FAQ ── */
        .faq-item { border: 1.5px solid #e5e7eb; border-radius: 1rem; margin-bottom: .75rem; overflow: hidden; transition: border-color .2s; }
        .faq-item.open { border-color: var(--brand); }
        .faq-q { padding: 1.1rem 1.5rem; font-weight: 700; font-size: .95rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 1rem; background: #fff; user-select: none; }
        .faq-a { padding: 0 1.5rem; max-height: 0; overflow: hidden; transition: max-height .3s ease, padding .3s; font-size: .9rem; color: var(--gray); line-height: 1.7; }
        .faq-a.open { max-height: 300px; padding: .75rem 1.5rem 1.25rem; }
        .faq-icon { transition: transform .3s; color: var(--brand); flex-shrink: 0; }
        .faq-item.open .faq-icon { transform: rotate(45deg); }

        /* ── CTA ── */
        .cta-section { background: linear-gradient(145deg, var(--brand-dark) 0%, var(--brand) 50%, color-mix(in srgb, var(--accent) 15%, var(--brand-dark))); position: relative; overflow: hidden; }
        .cta-section::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px); background-size: 40px 40px; }

        /* ── Footer ── */
        footer { background: #0f172a; color: #64748b; }
        .footer-brand { font-size: 1.35rem; font-weight: 900; color: #fff; }
        .footer-heading { color: #94a3b8; font-weight: 700; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 1rem; }
        footer a { color: #64748b; text-decoration: none; transition: color .2s; font-size: .88rem; }
        footer a:hover { color: #f1f5f9; }
        .footer-social a { width: 36px; height: 36px; background: rgba(255,255,255,.06); border-radius: .5rem; display: inline-flex; align-items: center; justify-content: center; color: #94a3b8 !important; transition: all .2s; }
        .footer-social a:hover { background: var(--brand); color: #fff !important; }
        .newsletter-input { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: #fff; border-radius: .625rem 0 0 .625rem; padding: .5rem 1rem; font-size: .875rem; outline: none; flex: 1; }
        .newsletter-input::placeholder { color: #475569; }
        .newsletter-btn { background: var(--brand); color: #fff; border: none; border-radius: 0 .625rem .625rem 0; padding: .5rem 1.25rem; font-weight: 700; font-size: .875rem; cursor: pointer; transition: background .2s; }
        .newsletter-btn:hover { background: var(--brand-dark); }

        /* ── Scroll to top ── */
        #scrollTop { position: fixed; bottom: 1.5rem; left: 1.5rem; width: 44px; height: 44px; background: var(--brand); color: #fff; border: none; border-radius: .75rem; display: none; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 8px 24px rgba(18,36,78,.4); transition: all .2s; z-index: 999; }
        #scrollTop:hover { background: var(--brand-dark); transform: translateY(-2px); }
        #scrollTop.show { display: flex; }

        /* ── Section default padding (overridable via media queries) ── */
        .lp-section { padding: 6rem 0; }
        .lp-section-cta { padding: 7rem 0; }
        .lp-footer { padding: 4rem 0 2rem; }

        /* ══ RESPONSIVE ═══════════════════════════════════════════════════════ */

        /* ── Tablet & below (< 992px) ── */
        @media (max-width: 991.98px) {
            .hero { padding: 5.5rem 0 3.5rem; }
            .hero-stat-bar { gap: 1.5rem; padding: 1rem 1.5rem; margin-top: 2rem; }

            /* Navbar collapsed — buttons stack vertically */
            .navbar-collapse .d-flex.align-items-center {
                flex-direction: column;
                align-items: stretch !important;
                gap: .5rem !important;
                padding: .75rem 0;
                border-top: 1px solid #f1f5f9;
                margin-top: .5rem;
            }
            .navbar-collapse .btn-lang,
            .navbar-collapse .btn-nav-login,
            .navbar-collapse .btn-nav-cta {
                width: 100%;
                text-align: center;
                justify-content: center;
                display: flex;
            }

            .lp-section, .lp-section-cta { padding: 4rem 0; }
        }

        /* ── Mobile (< 768px) ── */
        @media (max-width: 767.98px) {
            .hero { padding: 5rem 0 3rem; min-height: auto; }
            .hero-badge { font-size: .78rem; padding: .35rem .9rem; }
            .hero-title { font-size: clamp(1.9rem, 9vw, 2.6rem); }
            .hero-sub { font-size: 1rem; }
            .hero-trust { font-size: .77rem; line-height: 1.9; }

            /* Stat bar: 2×2 grid on mobile */
            .hero-stat-bar { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding: 1rem; justify-items: center; }
            .hero-stat-num { font-size: 1.5rem; }

            /* Feature cards: 2 per row on mobile */
            .feat-card { padding: 1.25rem; }
            .feat-icon { width: 44px; height: 44px; font-size: 1.1rem; margin-bottom: .75rem; }
            .feat-card h5 { font-size: .95rem; }
            .feat-card p { font-size: .82rem; }

            /* Section typography */
            .section-title { font-size: clamp(1.5rem, 6vw, 2rem); }
            .section-sub { font-size: .95rem; }
            .lp-section, .lp-section-cta { padding: 3.5rem 0; }

            /* Pricing */
            .price-card { padding: 2rem 1.25rem; }
            .price-amount { font-size: 2.75rem; }

            /* Trusted logos */
            .trust-logos { gap: 1.25rem; }
            .trust-logo { font-size: .95rem; }

            /* FAQ */
            .faq-q { font-size: .9rem; padding: 1rem 1.25rem; }
            .faq-a, .faq-a.open { padding-left: 1.25rem; padding-right: 1.25rem; }
        }

        /* ── Small mobile (< 576px) ── */
        @media (max-width: 575.98px) {
            .hero { padding: 4.5rem 0 2.5rem; }
            .hero-badge { font-size: .73rem; }
            .hero-title { font-size: clamp(1.75rem, 10vw, 2.4rem); line-height: 1.2; }
            .hero-sub { font-size: .95rem; }

            /* Stat bar stays 2×2 but tighter */
            .hero-stat-bar { gap: .75rem; padding: .875rem; }
            .hero-stat-num { font-size: 1.35rem; }
            .hero-stat-lbl { font-size: .72rem; }

            /* Hero CTA buttons fill width */
            .hero-cta-wrap { display: flex; flex-direction: column; }
            .hero-cta-wrap .btn-hero-primary,
            .hero-cta-wrap .btn-hero-secondary { width: 100%; text-align: center; justify-content: center; font-size: .95rem; padding: .8rem 1.5rem; }

            /* Sections */
            .lp-section, .lp-section-cta { padding: 3rem 0; }
            .lp-footer { padding: 2.5rem 0 1.5rem; }
            .section-pill { font-size: .72rem; }
            .section-title { font-size: 1.5rem; }

            /* Features compact on xs */
            .feat-card { padding: 1rem; }
            .feat-icon { width: 40px; height: 40px; font-size: 1rem; }

            /* Step */
            .step-num { width: 44px; height: 44px; font-size: 1.1rem; }
            .step-connector { margin-right: 1.375rem !important; }

            /* Pricing best tag */
            .price-best-tag { font-size: .72rem; padding: .3rem 1.1rem; }
            .price-amount { font-size: 2.4rem; }

            /* Testimonial */
            .testi-card { padding: 1.5rem; }

            /* CTA heading */
            .cta-section h2 { font-size: 1.75rem !important; }
            .cta-section p { font-size: 1rem !important; }

            /* Footer */
            .footer-brand { font-size: 1.15rem; }
            .footer-heading { font-size: .75rem; }
            .newsletter-input { font-size: .8rem; }
        }

        /* ── AOS: softer mobile animations (override 100px default translate) ── */
        @media (max-width: 767.98px) {
            [data-aos="fade-up"] {
                transform: translate3d(0, 24px, 0);
            }
            [data-aos] {
                transition-duration: 450ms !important;
                transition-delay: 0ms !important;
            }
        }
        @media (max-width: 575.98px) {
            [data-aos="fade-up"] {
                transform: translate3d(0, 16px, 0);
            }
            [data-aos] {
                transition-duration: 350ms !important;
            }
        }
    </style>
</head>

<body>

{{-- ── Navbar ── --}}
<nav class="navbar navbar-expand-lg sticky-top" id="navbar">
    <div class="container">
        <a class="navbar-brand" href="{{ route('welcome') }}">
            <span class="nb-icon"><i class="fas fa-crosshairs"></i></span>
            <span>{{ $isAr ? 'نقطة' : 'Nuqtah' }}<span class="nb-dot">.</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto gap-1 ms-4">
                <li class="nav-item"><a class="nav-link" href="#features">{{ $isAr ? 'المميزات' : 'Features' }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#how">{{ $isAr ? 'كيف يعمل؟' : 'How It Works' }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#pricing">{{ $isAr ? 'الأسعار' : 'Pricing' }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#testimonials">{{ $isAr ? 'آراء العملاء' : 'Reviews' }}</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">{{ $isAr ? 'الأسئلة الشائعة' : 'FAQ' }}</a></li>
            </ul>
            <div class="d-flex gap-2 align-items-center">
                {{-- Language Switcher --}}
                <a href="{{ route('lang.switch', $isAr ? 'en' : 'ar') }}" class="btn btn-lang">
                    <i class="fas fa-globe"></i>
                    {{ $isAr ? 'EN' : 'عر' }}
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-nav-cta">
                        <i class="fas fa-gauge-high me-1"></i>{{ $isAr ? 'لوحة التحكم' : 'Dashboard' }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-nav-login">{{ $isAr ? 'تسجيل الدخول' : 'Login' }}</a>
                    <a href="{{ route('register') }}" class="btn btn-nav-cta">
                        <i class="fas fa-rocket me-1"></i>{{ $isAr ? 'ابدأ مجاناً' : 'Get Started' }}
                    </a>
                @endauth
            </div>
        </div>
    </div>
</nav>

{{-- ── Hero ── --}}
<section class="hero text-white">
    <div class="hero-grid"></div>
    <div class="hero-glow-1"></div>
    <div class="hero-glow-2"></div>
    <div class="container position-relative" style="z-index:2">
        <div class="row align-items-center g-5">
            <div class="col-lg-5" data-aos="fade-up" data-aos-duration="800">
                <div class="hero-badge">
                    <span class="hero-badge-dot"></span>
                    {{ $isAr ? 'متاح الآن · نظام سحابي متكامل' : 'Live Now · Fully Cloud-Based' }}
                </div>
                <h1 class="hero-title mb-4">
                    @if($isAr)
                        أدِر متجرك<br>
                        <span class="gradient-text">بذكاء وسرعة</span><br>
                        من أي مكان
                    @else
                        Manage Your Store<br>
                        <span class="gradient-text">Smarter & Faster</span><br>
                        From Anywhere
                    @endif
                </h1>
                <p class="hero-sub mb-4">
                    {{ $isAr
                        ? 'منصة نقطة بيع سحابية تجمع المبيعات، المخزون، الحسابات، والتقارير في واجهة واحدة سلسة — بدون تعقيد ولا تقنية معقدة.'
                        : 'A cloud POS platform unifying sales, inventory, accounting, and reports in one seamless interface — no complexity, no technical expertise needed.' }}
                </p>
                <div class="hero-cta-wrap d-flex flex-wrap gap-3 mb-3">
                    <a href="{{ route('register') }}" class="btn btn-hero-primary">
                        <i class="fas fa-play me-2"></i>{{ $isAr ? 'ابدأ تجربة مجانية 14 يوم' : 'Start 14-Day Free Trial' }}
                    </a>
                    <a href="#features" class="btn btn-hero-secondary">
                        <i class="fas fa-circle-play me-2"></i>{{ $isAr ? 'اكتشف المميزات' : 'Explore Features' }}
                    </a>
                </div>
                <p class="hero-trust">
                    <i class="fas fa-shield-halved me-1"></i>
                    {{ $isAr
                        ? 'لا يلزم بطاقة ائتمانية &nbsp;·&nbsp; إلغاء في أي وقت &nbsp;·&nbsp; إعداد في 5 دقائق'
                        : 'No credit card required &nbsp;·&nbsp; Cancel anytime &nbsp;·&nbsp; Setup in 5 minutes' }}
                </p>
                <div class="hero-stat-bar">
                    <div class="hero-stat">
                        <div class="hero-stat-num" data-count="500">0</div>
                        <div class="hero-stat-lbl">{{ $isAr ? '+ متجر نشط' : '+ Active Stores' }}</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-num">99.9%</div>
                        <div class="hero-stat-lbl">{{ $isAr ? 'وقت التشغيل' : 'Uptime' }}</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-num" data-count="1000000">0</div>
                        <div class="hero-stat-lbl">{{ $isAr ? 'فاتورة معالجة' : 'Invoices Processed' }}</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-num">24/7</div>
                        <div class="hero-stat-lbl">{{ $isAr ? 'دعم فني' : 'Support' }}</div>
                    </div>
                </div>
            </div>

            {{-- POS Mockup --}}
            <div class="col-lg-7 d-none d-lg-block" data-aos="fade-up" data-aos-duration="900" data-aos-delay="100">
                <div class="position-relative ps-3">
                    <span class="pos-badge"><i class="fas fa-circle-check me-1"></i>{{ $isAr ? 'فاتورة مكتملة +275 ر.س' : 'Invoice Complete +275 SAR' }}</span>
                    <div class="pos-mockup">
                        <div class="pos-topbar">
                            <div class="pos-dots">
                                <span class="pos-dot-r"></span>
                                <span class="pos-dot-y"></span>
                                <span class="pos-dot-g"></span>
                            </div>
                            <div class="pos-title-bar"><i class="fas fa-crosshairs me-1"></i>{{ $isAr ? 'نقطة POS — نظام نقاط البيع' : 'NuqtahPOS — Point of Sale' }}</div>
                            <div style="font-size:.7rem;color:rgba(255,255,255,.35)">{{ now()->format('H:i') }}</div>
                        </div>
                        <div class="pos-body">
                            <div class="pos-left">
                                <div class="pos-search">
                                    <i class="fas fa-magnifying-glass" style="color:rgba(255,255,255,.35)"></i>
                                    <span>{{ $isAr ? 'البحث عن منتج أو باركود...' : 'Search product or barcode...' }}</span>
                                </div>
                                @foreach ($isAr
                                    ? [['🛒','عصير برتقال طازج','4.50 ر.س','x3'],['🥛','حليب كامل الدسم','7.25 ر.س','x2'],['🍞','خبز أبيض طازج','2.75 ر.س','x1'],['🧴','شامبو للشعر الجاف','18.00 ر.س','x1']]
                                    : [['🛒','Fresh Orange Juice','4.50 SAR','x3'],['🥛','Full-Fat Milk','7.25 SAR','x2'],['🍞','White Bread','2.75 SAR','x1'],['🧴','Hair Shampoo','18.00 SAR','x1']]
                                    as $item)
                                    <div class="pos-product-row">
                                        <div class="pos-product-img">{{ $item[0] }}</div>
                                        <div>
                                            <div class="pos-product-name">{{ $item[1] }}</div>
                                            <div class="pos-product-price">{{ $item[2] }}</div>
                                        </div>
                                        <span class="pos-product-qty">{{ $item[3] }}</span>
                                    </div>
                                @endforeach
                                <div class="pos-chart-bar mt-3">
                                    @foreach ([40,65,45,80,55,90,70,85,60,95,75,100] as $h)
                                        <div class="pos-bar" style="height:{{ $h }}%"></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="pos-right">
                                <div class="pos-total-box">
                                    <div class="pos-total-label">{{ $isAr ? 'الإجمالي' : 'Total' }}</div>
                                    <div class="pos-total-num">275.50</div>
                                    <div style="font-size:.6rem;color:rgba(255,255,255,.4)">{{ $isAr ? 'ريال سعودي' : 'SAR' }}</div>
                                </div>
                                <button class="pos-pay-btn"><i class="fas fa-credit-card me-1"></i>{{ $isAr ? 'دفع إلكتروني' : 'Card Payment' }}</button>
                                <button class="pos-pay-btn pos-pay-cash"><i class="fas fa-money-bill me-1"></i>{{ $isAr ? 'نقدي' : 'Cash' }}</button>
                                <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid rgba(255,255,255,.06)">
                                    <div style="font-size:.65rem;color:rgba(255,255,255,.4);margin-bottom:.4rem">{{ $isAr ? 'المبيعات اليوم' : "Today's Sales" }}</div>
                                    <div style="font-size:1rem;font-weight:800;color:#4ade80">12,450 {{ $isAr ? 'ر.س' : 'SAR' }}</div>
                                    <div style="font-size:.62rem;color:rgba(74,222,128,.6)"><i class="fas fa-arrow-up me-1"></i>{{ $isAr ? '+18% عن أمس' : '+18% vs yesterday' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span class="pos-badge-2"><i class="fas fa-chart-line me-1"></i>{{ $isAr ? 'مبيعات اليوم +18%' : "Today's Sales +18%" }}</span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Trusted by ── --}}
<div class="trusted-bar">
    <div class="container">
        <div class="d-flex align-items-center gap-4 flex-wrap justify-content-center">
            <span class="trusted-label">{{ $isAr ? 'يثق بنا' : 'Trusted By' }}</span>
            <div class="trust-logos">
                @foreach ($isAr
                    ? [['fa-store','سوبر ماركت'],['fa-utensils','مطاعم وكافيهات'],['fa-pills','صيدليات'],['fa-shirt','ملابس وأزياء'],['fa-gem','محلات الذهب'],['fa-mobile-screen','إلكترونيات']]
                    : [['fa-store','Supermarkets'],['fa-utensils','Restaurants & Cafes'],['fa-pills','Pharmacies'],['fa-shirt','Fashion & Apparel'],['fa-gem','Jewelry Stores'],['fa-mobile-screen','Electronics']]
                    as $logo)
                    <span class="trust-logo"><i class="fas {{ $logo[0] }}"></i>{{ $logo[1] }}</span>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── Features ── --}}
<section id="features" class="lp-section" style="background:#f8fafc;">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-pill">{{ $isAr ? 'المميزات' : 'Features' }}</span>
            <h2 class="section-title">{{ $isAr ? 'كل ما تحتاجه في مكان واحد' : 'Everything You Need in One Place' }}</h2>
            <p class="section-sub mx-auto">{{ $isAr
                ? 'منصة متكاملة تغطي كافة احتياجات إدارة متجرك من البيع حتى التقارير المالية.'
                : 'A complete platform covering all your store management needs from sales to financial reports.' }}</p>
        </div>
        <div class="row g-4">
            @foreach ([
                ['fa-cash-register',       '$_brandLight', '$_brand',  'نقطة بيع سريعة',      'Fast POS',               'واجهة بيع سلسة مع دعم الباركود والخصومات التلقائية وإدارة الكاشير.',                              'Smooth POS interface with barcode support, automatic discounts, and cashier management.'],
                ['fa-boxes-stacked',       '#f0fdf4',     '#059669',  'إدارة المخزون',        'Inventory Management',   'تتبع المخزون لحظياً مع تنبيهات نفاد المخزون وإدارة المستودعات المتعددة.',                           'Real-time inventory tracking with low-stock alerts and multi-warehouse management.'],
                ['fa-file-invoice-dollar', '$_accentLight','$_accent','محاسبة متكاملة',       'Integrated Accounting',  'قيود يومية، ميزانية عمومية، قائمة دخل، وتقارير مالية احترافية.',                                    'Daily entries, balance sheet, income statement, and professional financial reports.'],
                ['fa-chart-column',        '#fff7ed', '#ea580c', 'تقارير ذكية',          'Smart Reports',          'تقارير مبيعات، أرباح، مرتجعات وتدفق نقدي مع تصدير Excel وPDF.',                                    'Sales, profit, returns, and cash-flow reports with Excel & PDF export.'],
                ['fa-people-group',        '#f0f9ff', '#0284c7', 'إدارة العملاء',        'Customer Management',    'مجموعات العملاء، مستويات الأسعار، وسجل الشراء الكامل.',                                             'Customer groups, pricing tiers, and complete purchase history.'],
                ['fa-truck-fast',          '#fefce8', '#ca8a04', 'أوامر الشراء',         'Purchase Orders',        'إدارة الموردين، أوامر الشراء، واستلام البضاعة آلياً.',                                              'Supplier management, purchase orders, and automatic stock reception.'],
                ['fa-percent',             '#fff1f2', '#e11d48', 'عروض وترقيات',         'Promotions',             'خصومات بالنسبة أو القيمة، اشترِ X واحصل على Y، وبطاقات هدايا.',                                    'Percentage or fixed discounts, Buy X Get Y, and gift cards.'],
                ['fa-shield-halved',       '#f5f3ff', '#6d28d9', 'أمان متقدم',           'Advanced Security',      'صلاحيات دقيقة، تحقق ثنائي 2FA، وسجل تدقيق كامل لكل عملية.',                                       'Granular permissions, 2FA verification, and a complete audit log for every operation.'],
            ] as $i => $f)
                <div class="col-6 col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="{{ ($i % 4) * 80 }}">
                    <div class="feat-card">
                        @php
                            $featureBg  = str_starts_with($f[1], '$') ? ${ltrim($f[1], '$')} : $f[1];
                            $featureClr = str_starts_with($f[2], '$') ? ${ltrim($f[2], '$')} : $f[2];
                        @endphp
                        <div class="feat-icon" style="background:{{ $featureBg }};color:{{ $featureClr }}">
                            <i class="fas {{ $f[0] }}"></i>
                        </div>
                        <h5>{{ $isAr ? $f[3] : $f[4] }}</h5>
                        <p>{{ $isAr ? $f[5] : $f[6] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── How it works ── --}}
<section id="how" class="lp-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-pill">{{ $isAr ? 'كيف يعمل؟' : 'How It Works' }}</span>
            <h2 class="section-title">{{ $isAr ? 'ابدأ في 3 خطوات بسيطة' : 'Start in 3 Simple Steps' }}</h2>
            <p class="section-sub mx-auto">{{ $isAr
                ? 'بدون أي خبرة تقنية — النظام جاهز للعمل فور التسجيل.'
                : 'No technical experience needed — the system is ready to go right after registration.' }}</p>
        </div>
        <div class="row g-5 align-items-center">
            <div class="col-lg-4">
                @foreach ([
                    ['1','fa-user-plus', '$_brand',  $isAr ? 'سجّل متجرك'      : 'Register Your Store',    $isAr ? 'أنشئ حسابك مجاناً في أقل من دقيقة. لا تحتاج لبطاقة ائتمانية.'                       : 'Create your free account in under a minute. No credit card needed.'],
                    ['2','fa-sliders',   '$_accent', $isAr ? 'خصّص النظام'      : 'Customize the System',   $isAr ? 'أضف منتجاتك، مستخدميك، وفروعك بضغطات بسيطة.'                                         : 'Add your products, users, and branches with a few simple clicks.'],
                    ['3','fa-rocket',    '#ea580c', $isAr ? 'ابدأ البيع فوراً' : 'Start Selling Now',       $isAr ? 'النظام جاهز للعمل على الحاسب، الجوال، أو الجهاز اللوحي.'                             : 'Works on desktop, mobile, or tablet — right out of the box.'],
                ] as $j => $step)
                    <div class="d-flex gap-3 align-items-start mb-4" data-aos="fade-up" data-aos-delay="{{ $j * 150 }}">
                        <div class="step-num flex-shrink-0">{{ $step[0] }}</div>
                        <div class="pt-1">
                            <div style="font-weight:800;color:var(--dark);margin-bottom:.25rem">
                                @php $stepClr = str_starts_with($step[2], '$') ? ${ltrim($step[2], '$')} : $step[2]; @endphp
                                <i class="fas {{ $step[1] }} me-2" style="color:{{ $stepClr }}"></i>{{ $step[3] }}
                            </div>
                            <p class="text-muted small mb-0" style="line-height:1.65">{{ $step[4] }}</p>
                        </div>
                    </div>
                    @if ($j < 2)
                        <div class="step-connector me-4" style="margin-right:1.625rem!important"></div>
                    @endif
                @endforeach
            </div>
            <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
                <div style="background:linear-gradient(135deg,{{ $_brandDark }},{{ $_brand }});border-radius:1.5rem;padding:2.5rem;box-shadow:0 30px 80px rgba(0,0,0,.2)">
                    <div class="row g-3">
                        @foreach ([
                            ['fa-gauge-high',    '#60a5fa', $isAr?'لوحة التحكم':'Dashboard',  $isAr?'إجمالي اليوم':"Today's Total",  $isAr?'12,450 ر.س':'12,450 SAR', $isAr?'↑ 18%':'↑ 18%', '#4ade80'],
                            ['fa-cart-shopping', '#a78bfa', $isAr?'الفواتير':'Invoices',        $isAr?'فواتير اليوم':"Today's Invoices",$isAr?'47 فاتورة':'47 Invoices',  $isAr?'↑ 12%':'↑ 12%', '#a78bfa'],
                            ['fa-boxes-stacked', '#34d399', $isAr?'المخزون':'Inventory',        $isAr?'منتجات نشطة':'Active Products',  $isAr?'1,248 منتج':'1,248 Items', $isAr?'↓ 2 نفد':'↓ 2 low','#f87171'],
                            ['fa-users',         '#fb923c', $isAr?'العملاء':'Customers',         $isAr?'عملاء جدد':'New Customers',      $isAr?'23 عميل':'23 Customers',   $isAr?'↑ 5%':'↑ 5%',  '#4ade80'],
                        ] as $card)
                            <div class="col-6">
                                <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:1rem;padding:1.25rem">
                                    <div style="font-size:.7rem;color:rgba(255,255,255,.5);margin-bottom:.5rem">
                                        <i class="fas {{ $card[0] }} me-1" style="color:{{ $card[1] }}"></i>{{ $card[2] }}
                                    </div>
                                    <div style="font-size:.8rem;color:rgba(255,255,255,.5);margin-bottom:.2rem">{{ $card[3] }}</div>
                                    <div style="font-size:1.2rem;font-weight:900;color:#fff">{{ $card[4] }}</div>
                                    <div style="font-size:.72rem;color:{{ $card[6] }};margin-top:.25rem">{{ $card[5] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Pricing ── --}}
<section id="pricing" class="lp-section" style="background:#f8fafc;">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-pill">{{ $isAr ? 'الأسعار' : 'Pricing' }}</span>
            <h2 class="section-title">{{ $isAr ? 'خطة مناسبة لكل حجم' : 'A Plan for Every Size' }}</h2>
            <p class="section-sub mx-auto">{{ $isAr
                ? 'جميع الخطط تشمل تجربة مجانية 14 يوماً بدون بطاقة ائتمانية.'
                : 'All plans include a 14-day free trial with no credit card required.' }}</p>
        </div>
        <div class="row g-4 justify-content-center">
            @php
                $planColors = [
                    'basic'      => '#6b7280',
                    'pro'        => $_brand,
                    'enterprise' => $_accent,
                ];
                $bestPlan = 'pro';
            @endphp

            @forelse($plans as $plan)
                @php
                    $isBest   = $plan->id === $bestPlan;
                    $clr      = $planColors[$plan->id] ?? '#374151';
                    $btnStyle = $isBest
                        ? "background:linear-gradient(135deg,{$_brand},{$_accent});color:#fff;border:none;"
                        : 'border:2px solid #e5e7eb;background:#fff;color:var(--dark);';
                @endphp
                <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="{{ $loop->index * 100 }}">
                    <div class="price-card {{ $isBest ? 'best' : '' }}">
                        @if ($isBest)
                            <span class="price-best-tag">⭐ {{ $isAr ? 'الأكثر شيوعاً' : 'Most Popular' }}</span>
                        @endif
                        <div class="mb-4">
                            <span class="badge mb-3" style="background:{{ $clr }};color:#fff;font-size:.75rem;padding:.4rem .9rem;border-radius:.5rem">
                                {{ strtoupper($plan->id) }}
                            </span>
                            <div class="fw-bold fs-5 mb-1">{{ $plan->name }}</div>
                            <div class="d-flex align-items-end gap-1 mb-1">
                                <span class="price-amount" style="color:{{ $clr }}">${{ number_format($plan->monthly_price, 0) }}</span>
                                <span class="price-period mb-2">/ {{ $isAr ? 'شهر' : 'mo' }}</span>
                            </div>
                            @if ($plan->annual_price)
                                <div class="text-muted small">
                                    {{ $isAr ? 'أو' : 'or' }} ${{ number_format($plan->annual_price, 0) }}/{{ $isAr ? 'سنة' : 'yr' }}
                                    @php $save = $plan->annualSavings(); @endphp
                                    @if ($save > 0)
                                        <span class="badge bg-success ms-1" style="font-size:.68rem">{{ $isAr ? 'وفّر' : 'Save' }} ${{ number_format($save, 0) }}</span>
                                    @endif
                                </div>
                            @endif
                            <div class="text-muted small mt-1">
                                @if ($plan->max_users)
                                    {{ $isAr ? 'حتى' : 'Up to' }} {{ $plan->max_users }} {{ $isAr ? 'مستخدم' : 'users' }}
                                @else
                                    {{ $isAr ? 'مستخدمون غير محدودون' : 'Unlimited users' }}
                                @endif
                                &nbsp;·&nbsp;
                                @if ($plan->max_products)
                                    {{ $plan->max_products }} {{ $isAr ? 'منتج' : 'products' }}
                                @else
                                    {{ $isAr ? 'منتجات غير محدودة' : 'Unlimited products' }}
                                @endif
                            </div>
                        </div>
                        {{-- Marketing highlights (freeform text bullets) --}}
                        @if (!empty($plan->features))
                        <ul class="price-feature-list mb-3">
                            @foreach ($plan->features as $feat)
                                <li>
                                    <i class="fas fa-circle-check pfl-check fa-sm mt-1"></i>
                                    <span>{{ is_array($feat) ? ($isAr ? ($feat['ar'] ?? $feat['en'] ?? '') : ($feat['en'] ?? $feat['ar'] ?? '')) : $feat }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @endif

                        {{-- Module chips from feature_flags --}}
                        @php
                            $flags = $plan->feature_flags ?? [];
                            $maxVisible = 8;
                            $visibleFlags = array_slice($flags, 0, $maxVisible);
                            $hiddenCount  = max(0, count($flags) - $maxVisible);
                        @endphp
                        @if (!empty($flags))
                        <div class="mb-4">
                            <div class="d-flex flex-wrap gap-1">
                                @foreach ($visibleFlags as $flagKey)
                                    @php $mod = $allModules[$flagKey] ?? null; @endphp
                                    @if ($mod)
                                    <span class="badge rounded-pill px-2 py-1"
                                          style="background:{{ $clr }}18;color:{{ $clr }};border:1px solid {{ $clr }}38;font-size:.7rem;font-weight:500;"
                                          title="{{ $isAr ? ($mod['ar'] ?? '') : ($mod['en'] ?? '') }}">
                                        <i class="fas {{ $mod['icon'] }} me-1" style="font-size:.65rem"></i>{{ $isAr ? ($mod['ar'] ?? $mod['en']) : ($mod['en'] ?? $mod['ar']) }}
                                    </span>
                                    @endif
                                @endforeach
                                @if ($hiddenCount > 0)
                                <span class="badge rounded-pill px-2 py-1"
                                      style="background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;font-size:.7rem;font-weight:500;">
                                    +{{ $hiddenCount }} {{ $isAr ? 'أكثر' : 'more' }}
                                </span>
                                @endif
                            </div>
                        </div>
                        @elseif (empty($plan->features))
                        <p class="text-muted small mb-4">{{ $isAr ? 'راجع التفاصيل عند التسجيل' : 'See details on sign-up' }}</p>
                        @endif
                        <a href="{{ route('register') }}" class="btn w-100 fw-bold py-2" style="{{ $btnStyle }}border-radius:.75rem;font-size:.95rem;">
                            {{ $isAr ? "ابدأ تجربة مجانية {$plan->trial_days} يوم" : "Start {$plan->trial_days}-Day Free Trial" }}
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center text-muted py-5">
                    <i class="fas fa-circle-notch fa-spin fa-2x mb-3 text-secondary"></i>
                    <p>{{ $isAr ? 'لا توجد خطط متاحة حالياً' : 'No plans available at the moment' }}</p>
                </div>
            @endforelse
        </div>
        <p class="text-center text-muted mt-4 small">
            <i class="fas fa-shield-halved me-1 text-success"></i>
            {{ $isAr
                ? 'جميع الخطط تشمل SSL، نسخ احتياطي يومي، وضمان استرداد المال خلال 30 يوماً.'
                : 'All plans include SSL, daily backups, and a 30-day money-back guarantee.' }}
        </p>
    </div>
</section>

{{-- ── Testimonials ── --}}
<section id="testimonials" class="lp-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-pill">{{ $isAr ? 'آراء العملاء' : 'Customer Reviews' }}</span>
            <h2 class="section-title">{{ $isAr ? 'يثقون بنا كل يوم' : 'They Trust Us Every Day' }}</h2>
            <p class="section-sub mx-auto">{{ $isAr
                ? 'أكثر من 500 متجر يديرون أعمالهم يومياً باستخدام NuqtahPOS.'
                : 'Over 500 stores manage their operations daily using NuqtahPOS.' }}</p>
        </div>
        <div class="row g-4">
            @foreach ($isAr ? [
                ['أحمد الشمري',  'صاحب سلسلة مطاعم (5 فروع)',      '$_brand',  'منذ تحولنا إلى نقطة POS انخفض وقت الدفع بنسبة 40٪، وأصبح بإمكاني متابعة جميع الفروع ومقارنة مبيعاتها من لوحة تحكم واحدة في أي وقت ومن أي مكان.', 5],
                ['سارة العتيبي', 'مديرة سلسلة متاجر ملابس',         '$_accent', 'التقارير المالية أصبحت جاهزة في ثوانٍ بدلاً من ساعات. وفّر النظام على فريقي أكثر من 20 ساعة أسبوعياً كانت تُهدر في الجداول الإلكترونية.', 5],
                ['محمد الغامدي', 'صاحب سلسلة صيدليات',             '#059669', 'إدارة انتهاء الصلاحية والمخزون أصبحت تلقائية تماماً. فريق الدعم الفني ممتاز ومتجاوب على مدار الساعة. أنصح به بشدة لكل صيدلي.', 5],
            ] : [
                ['Ahmed Al-Shammari',  'Owner of 5-Branch Restaurant Chain', '$_brand',  'Since switching to NuqtahPOS, checkout time dropped by 40%. I can now monitor all my branches and compare sales from a single dashboard — anytime, anywhere.', 5],
                ['Sarah Al-Otaibi',    'Fashion Retail Chain Manager',        '$_accent', 'Financial reports are ready in seconds instead of hours. The system saved my team over 20 hours a week that were wasted on spreadsheets.', 5],
                ['Mohammed Al-Ghamdi', 'Pharmacy Chain Owner',                '#059669', 'Expiry date and inventory management became completely automatic. The support team is excellent and available around the clock. Highly recommended.', 5],
            ] as $i => $t)
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="{{ $i * 100 }}">
                    <div class="testi-card h-100">
                        <div class="testi-quote">"</div>
                        <div class="mb-3">
                            @for ($s = 0; $s < $t[4]; $s++)
                                <i class="fas fa-star text-warning" style="font-size:.85rem"></i>
                            @endfor
                        </div>
                        <p class="testi-text mb-4">{{ $t[3] }}</p>
                        <div class="d-flex align-items-center gap-3 mt-auto">
                            @php $testiClr = str_starts_with($t[2], '$') ? ${ltrim($t[2], '$')} : $t[2]; @endphp
                            <div class="testi-avatar" style="background:{{ $testiClr }}">{{ mb_substr($t[0], 0, 1) }}</div>
                            <div>
                                <div class="fw-bold small">{{ $t[0] }}</div>
                                <div class="text-muted" style="font-size:.78rem">{{ $t[1] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── FAQ ── --}}
<section id="faq" class="lp-section" style="background:#f8fafc;">
    <div class="container" style="max-width:750px">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="section-pill">{{ $isAr ? 'الأسئلة الشائعة' : 'FAQ' }}</span>
            <h2 class="section-title">{{ $isAr ? 'لديك سؤال؟' : 'Got a Question?' }}</h2>
        </div>
        @foreach ($isAr ? [
            ['هل يمكنني تجربة النظام مجاناً؟',                 'نعم! جميع الخطط تأتي بفترة تجربة مجانية 14 يوماً بدون إدخال بيانات بطاقة ائتمانية. يمكنك الإلغاء في أي وقت خلال فترة التجربة.'],
            ['هل بياناتي آمنة على النظام؟',                    'نعم تماماً. نستخدم تشفير SSL لجميع الاتصالات، وتعزل بيانات كل متجر عن الآخر في قاعدة بيانات مستقلة، مع نسخ احتياطي يومي تلقائي.'],
            ['هل يعمل النظام على الجوال والتابلت؟',            'نعم، النظام متوافق تماماً مع جميع الأجهزة والمتصفحات. لا تحتاج لتنزيل أي تطبيق — يعمل مباشرة من المتصفح.'],
            ['كيف يتم الدفع؟ هل هناك عقد سنوي؟',              'يمكنك الاختيار بين الدفع الشهري أو السنوي (مع خصم يصل إلى 20%). لا توجد عقود إلزامية — يمكنك الإلغاء في أي وقت.'],
            ['هل يمكنني الترقية أو تخفيض خطتي لاحقاً؟',      'بالتأكيد. يمكنك تغيير خطتك في أي وقت من لوحة التحكم. الترقية فورية والتخفيض يسري في دورة الفوترة التالية.'],
            ['هل يدعم النظام اللغة العربية؟',                  'نعم، النظام يدعم اللغة العربية والإنجليزية بالكامل مع دعم الاتجاه من اليمين إلى اليسار (RTL).'],
        ] : [
            ['Can I try the system for free?',                  'Yes! All plans come with a 14-day free trial — no credit card required. Cancel anytime during the trial period.'],
            ['Is my data safe?',                                'Absolutely. We use SSL encryption for all connections, each store\'s data is isolated in its own database, and automated daily backups are included.'],
            ['Does it work on mobile and tablet?',              'Yes, the system is fully compatible with all devices and browsers. No app download needed — it runs directly in the browser.'],
            ['How does billing work? Is there an annual contract?','Choose between monthly or annual billing (up to 20% discount). No binding contracts — cancel anytime.'],
            ['Can I upgrade or downgrade my plan later?',       'Absolutely. Change your plan anytime from the control panel. Upgrades are instant; downgrades apply at the next billing cycle.'],
            ['Is Arabic language supported?',                   'Yes, the system fully supports both Arabic and English with right-to-left (RTL) layout support.'],
        ] as $i => $q)
            <div class="faq-item" data-aos="fade-up" data-aos-delay="{{ $i * 50 }}">
                <div class="faq-q" data-action="toggle-faq">
                    {{ $q[0] }}
                    <i class="fas fa-plus faq-icon"></i>
                </div>
                <div class="faq-a">{{ $q[1] }}</div>
            </div>
        @endforeach
    </div>
</section>

{{-- ── CTA ── --}}
<section class="cta-section lp-section-cta text-white">
    <div class="container text-center position-relative" style="z-index:1" data-aos="fade-up">
        <div class="hero-badge justify-content-center mb-4" style="display:inline-flex">
            <span class="hero-badge-dot"></span>
            {{ $isAr ? 'أكثر من 500 متجر يثق بنا' : 'Trusted by 500+ stores' }}
        </div>
        <h2 style="font-size:clamp(2rem,4vw,3rem);font-weight:900;letter-spacing:-.03em;" class="mb-3">
            @if($isAr)
                جاهز لتطوير متجرك؟<br>
                <span class="gradient-text">ابدأ اليوم مجاناً</span>
            @else
                Ready to Grow Your Store?<br>
                <span class="gradient-text">Start Free Today</span>
            @endif
        </h2>
        <p class="mb-5 opacity-75" style="font-size:1.15rem;max-width:480px;margin:auto;line-height:1.7">
            {{ $isAr
                ? 'انضم إلى مئات المتاجر التي تدير أعمالها بذكاء. لا بطاقة ائتمانية، لا التزام.'
                : 'Join hundreds of stores managing their business smarter. No credit card, no commitment.' }}
        </p>
        <div class="d-flex flex-wrap gap-3 justify-content-center mb-4">
            <a href="{{ route('register') }}" class="btn btn-hero-primary" style="font-size:1.1rem;padding:.9rem 2.75rem">
                <i class="fas fa-play me-2"></i>{{ $isAr ? 'ابدأ تجربتك المجانية' : 'Start Your Free Trial' }}
            </a>
            <a href="{{ route('login') }}" class="btn btn-hero-secondary">
                <i class="fas fa-right-to-bracket me-2"></i>{{ $isAr ? 'لديّ حساب بالفعل' : 'I Already Have an Account' }}
            </a>
        </div>
        <p class="hero-trust">
            <i class="fas fa-shield-halved me-1"></i>
            {{ $isAr ? 'آمن · موثوق · سهل الاستخدام' : 'Secure · Reliable · Easy to Use' }}
        </p>
    </div>
</section>

{{-- ── Footer ── --}}
<footer class="lp-footer">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="footer-brand mb-2 d-flex align-items-center gap-2">
                    @if($branding?->logo_path)
                        <img src="{{ Storage::url($branding->logo_path) }}" alt="{{ $_appName }}"
                             style="max-height:30px;max-width:100px;object-fit:contain;filter:brightness(0) invert(1)">
                    @else
                        <span style="width:30px;height:30px;background:linear-gradient(135deg,var(--brand),var(--accent));border-radius:.45rem;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:.8rem;flex-shrink:0">
                            <i class="fas fa-crosshairs"></i>
                        </span>
                        {{ $_appName }}<span style="color:var(--accent)"></span>
                    @endif
                </div>
                <p class="small mb-3" style="line-height:1.7">{{ $isAr
                    ? 'نظام نقطة بيع سحابي متكامل للشركات العربية الصغيرة والمتوسطة. إدارة أذكى، مبيعات أكثر.'
                    : 'A complete cloud POS system for small and medium businesses. Smarter management, more sales.' }}</p>
                <div class="footer-social d-flex gap-2">
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="footer-heading">{{ $isAr ? 'المنتج' : 'Product' }}</div>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#features">{{ $isAr ? 'المميزات' : 'Features' }}</a></li>
                    <li class="mb-2"><a href="#pricing">{{ $isAr ? 'الأسعار' : 'Pricing' }}</a></li>
                    <li class="mb-2"><a href="#how">{{ $isAr ? 'كيف يعمل؟' : 'How It Works' }}</a></li>
                    <li class="mb-2"><a href="{{ route('register') }}">{{ $isAr ? 'تسجيل جديد' : 'Sign Up' }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-2">
                <div class="footer-heading">{{ $isAr ? 'الشركة' : 'Company' }}</div>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#">{{ $isAr ? 'من نحن' : 'About Us' }}</a></li>
                    <li class="mb-2"><a href="#">{{ $isAr ? 'المدونة' : 'Blog' }}</a></li>
                    <li class="mb-2"><a href="#">{{ $isAr ? 'التواصل' : 'Contact' }}</a></li>
                    <li class="mb-2"><a href="#">{{ $isAr ? 'سياسة الخصوصية' : 'Privacy Policy' }}</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <div class="footer-heading">{{ $isAr ? 'ابقَ على اطلاع' : 'Stay Updated' }}</div>
                <p class="small mb-3">{{ $isAr
                    ? 'احصل على آخر التحديثات والميزات الجديدة مباشرة في بريدك.'
                    : 'Get the latest updates and new features delivered to your inbox.' }}</p>
                <div class="d-flex">
                    <input type="email" class="newsletter-input" placeholder="{{ $isAr ? 'بريدك الإلكتروني' : 'Your email address' }}">
                    <button class="newsletter-btn">{{ $isAr ? 'اشترك' : 'Subscribe' }}</button>
                </div>
                <p class="small mt-2" style="color:#334155;font-size:.75rem">{{ $isAr
                    ? 'لن نرسل لك رسائل غير مرغوب فيها. إلغاء الاشتراك في أي وقت.'
                    : 'No spam. Unsubscribe anytime.' }}</p>
            </div>
        </div>
        <hr style="border-color:#1e293b;margin:1.5rem 0">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small">
            <span>
                @if($_footerText)
                    {!! e($_footerText) !!}
                @else
                    © {{ date('Y') }} {{ $_appName }}. {{ $isAr ? 'جميع الحقوق محفوظة.' : 'All rights reserved.' }}
                @endif
                @if(!$_hidePowered)
                    &nbsp;<span style="opacity:.5">· Powered by NuqtahPOS</span>
                @endif
            </span>
            <div class="d-flex gap-3">
                <a href="#">{{ $isAr ? 'سياسة الخصوصية' : 'Privacy Policy' }}</a>
                <a href="#">{{ $isAr ? 'الشروط والأحكام' : 'Terms & Conditions' }}</a>
                @if($_supportEmail)
                    <a href="mailto:{{ $_supportEmail }}">{{ $isAr ? 'الدعم الفني' : 'Support' }}</a>
                @elseif($_website)
                    <a href="{{ $_website }}" target="_blank" rel="noopener">{{ $isAr ? 'الدعم الفني' : 'Support' }}</a>
                @else
                    <a href="#">{{ $isAr ? 'الدعم الفني' : 'Support' }}</a>
                @endif
            </div>
        </div>
    </div>
</footer>

<button id="scrollTop" title="{{ $isAr ? 'للأعلى' : 'Back to top' }}"><i class="fas fa-arrow-up"></i></button>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script nonce="{{ app('csp-nonce') }}">
    AOS.init({
        once     : true,
        easing   : 'ease-out-cubic',
        offset   : window.innerWidth < 576 ? 30 : 80,
        duration : window.innerWidth < 576 ? 450 : 700,
        delay    : 0,
    });

    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 20);
        document.getElementById('scrollTop').classList.toggle('show', window.scrollY > 400);
    });

    document.getElementById('scrollTop').addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const t = document.querySelector(a.getAttribute('href'));
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        });
    });

    // Counter animation
    function animateCounter(el) {
        const target = parseInt(el.dataset.count);
        const step   = target / (2000 / 16);
        let current  = 0;
        const timer  = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = '+' + Math.floor(current).toLocaleString('{{ $isAr ? 'ar-EG' : 'en-US' }}');
        }, 16);
    }
    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { animateCounter(e.target); observer.unobserve(e.target); } });
    }, { threshold: .5 });
    document.querySelectorAll('[data-count]').forEach(el => observer.observe(el));

    // FAQ accordion
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-action="toggle-faq"]');
        if (!btn) return;
        const item  = btn.parentElement;
        const ans   = item.querySelector('.faq-a');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(f => {
            f.classList.remove('open');
            f.querySelector('.faq-a').classList.remove('open');
        });
        if (!isOpen) { item.classList.add('open'); ans.classList.add('open'); }
    });
</script>
</body>
</html>
