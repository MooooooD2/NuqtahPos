<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CashbackController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceSessionController;
use App\Http\Controllers\DynamicPricingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KitchenDisplayController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\QrOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WasteController;
use App\Http\Middleware\CheckSubscriptionActive;
use App\Models\PaymentAccount;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

// ── Landing page (default root) ───────────────────────────────────────────
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

    // Load master-tenant branding (if any) so the landing page respects white-label settings.
    // This is safe to attempt from the public context — tenant may not be initialised here,
    // so we do a direct DB lookup on the central connection.
    $branding = null;

    try {
        $masterId = config('tenancy.master_tenant');
        if ($masterId) {
            $branding = Illuminate\Support\Facades\Cache::remember(
                "wl_branding_landing:{$masterId}",
                3600,
                fn () => App\Models\WhiteLabel::on('mysql')->where('tenant_id', $masterId)->first(),
            );
        }
    } catch (Throwable) {
        // Not critical — fall through to default colours
    }

    $allModules = App\Services\PlanFeatureService::allModules();

    return view('welcome', compact('plans', 'branding', 'allModules'));
})->name('welcome');

Route::redirect('/home', '/');

// ── PWA static-file fallbacks ──────────────────────────────────────────────
// Apache/Nginx serve these directly from public/ when the file exists.
// These routes are the fallback for servers that don't have the static files
// (e.g. production before the first full deploy).

Route::get('/site.webmanifest', function () {
    $branding = null;

    try {
        $masterId = config('tenancy.master_tenant');
        if ($masterId) {
            $branding = Illuminate\Support\Facades\Cache::remember(
                "wl_branding_landing:{$masterId}",
                3600,
                fn () => App\Models\WhiteLabel::on('mysql')->where('tenant_id', $masterId)->first(),
            );
        }
    } catch (Throwable) {
        // fall through
    }

    $appName = $branding?->app_name ?? config('app.name', 'POS System');
    $themeColor = $branding?->primary_color ?? '#1e293b';

    // Use route() so URLs are absolute and work regardless of subpath install
    $iconUrl = route('icons.svg');
    $posUrl = route('pos');
    $whUrl = route('warehouses');
    $kitUrl = route('kitchen');

    $manifest = [
        'name' => $appName . ' — نظام نقطة البيع',
        'short_name' => $appName,
        'description' => 'Offline-capable Point of Sale with AI forecasting',
        'start_url' => $posUrl . '?pwa=1',
        'scope' => url('/'),
        'display' => 'standalone',
        'display_override' => ['window-controls-overlay', 'standalone', 'browser'],
        'background_color' => $themeColor,
        'theme_color' => $themeColor,
        'orientation' => 'landscape-primary',
        'lang' => 'ar',
        'dir' => 'rtl',
        'categories' => ['business', 'productivity'],
        'shortcuts' => [
            ['name' => 'نقطة البيع',  'short_name' => 'POS',     'url' => $posUrl . '?pwa=1', 'icons' => [['src' => $iconUrl, 'sizes' => 'any', 'type' => 'image/svg+xml']]],
            ['name' => 'المخزون',     'short_name' => 'Stock',   'url' => $whUrl . '?pwa=1', 'icons' => [['src' => $iconUrl, 'sizes' => 'any', 'type' => 'image/svg+xml']]],
            ['name' => 'شاشة المطبخ', 'short_name' => 'Kitchen', 'url' => $kitUrl . '?pwa=1', 'icons' => [['src' => $iconUrl, 'sizes' => 'any', 'type' => 'image/svg+xml']]],
        ],
        'icons' => [
            ['src' => $iconUrl, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ['src' => $iconUrl, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
        ],
        'related_applications' => [],
        'prefer_related_applications' => false,
        'protocol_handlers' => [['protocol' => 'web+pos', 'url' => $posUrl . '?barcode=%s']],
    ];

    return response()->json($manifest)
        ->header('Content-Type', 'application/manifest+json')
        ->header('Cache-Control', 'public, max-age=86400');
})->name('manifest');

Route::get('/icons/icon.svg', function () {
    $branding = null;

    try {
        $masterId = config('tenancy.master_tenant');
        if ($masterId) {
            $branding = Illuminate\Support\Facades\Cache::remember(
                "wl_branding_landing:{$masterId}",
                3600,
                fn () => App\Models\WhiteLabel::on('mysql')->where('tenant_id', $masterId)->first(),
            );
        }
    } catch (Throwable) {
        // fall through
    }

    $bg = $branding?->primary_color ?? '#1e293b';
    $accent = $branding?->secondary_color ?? '#38bdf8';
    $label = $branding?->app_name ?? 'POS';
    $label = mb_strtoupper(mb_substr($label, 0, 3));

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="80" fill="{$bg}"/>
  <rect x="96" y="200" width="320" height="200" rx="16" fill="#334155"/>
  <rect x="120" y="120" width="272" height="100" rx="12" fill="#0f172a"/>
  <rect x="132" y="132" width="248" height="76" rx="8" fill="{$accent}" opacity=".15"/>
  <text x="256" y="188" text-anchor="middle" font-family="Arial,sans-serif" font-size="48" font-weight="700" fill="{$accent}">{$label}</text>
  <circle cx="176" cy="264" r="14" fill="#475569"/>
  <circle cx="256" cy="264" r="14" fill="#475569"/>
  <circle cx="336" cy="264" r="14" fill="#475569"/>
  <circle cx="176" cy="320" r="14" fill="#475569"/>
  <circle cx="256" cy="320" r="14" fill="{$accent}"/>
  <circle cx="336" cy="320" r="14" fill="#475569"/>
  <rect x="140" y="368" width="232" height="16" rx="8" fill="#475569"/>
  <rect x="230" y="362" width="52" height="6" rx="3" fill="#94a3b8"/>
</svg>
SVG;

    return response($svg)
        ->header('Content-Type', 'image/svg+xml')
        ->header('Cache-Control', 'public, max-age=86400');
})->name('icons.svg');

// ── Auth ──────────────────────────────────────────────────────────────────
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ── Register (new store sign-up) ──────────────────────────────────────────
Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,60')->name('register.post');

// ── 2FA (FIX-5) ───────────────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('2fa')->name('2fa.')->group(function () {
    Route::get('/verify', [TwoFactorController::class, 'showVerify'])->name('verify');
    Route::post('/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('verify.post');
    Route::get('/setup', [TwoFactorController::class, 'showSetup'])->name('setup');
    Route::post('/setup/confirm', [TwoFactorController::class, 'confirmSetup'])->name('setup.confirm');
    Route::post('/disable', [TwoFactorController::class, 'disable'])->name('disable');
    Route::get('/recover', [TwoFactorController::class, 'showRecover'])->name('recover');
    Route::post('/recover', [TwoFactorController::class, 'recoverWithCode'])->middleware('throttle:5,1')->name('recover.post');
});
Route::get('/lang/{locale}', [LanguageController::class, 'switch'])->where('locale', 'ar|en')->name('lang.switch');
Route::get('/lang/{locale}/translations', [LanguageController::class, 'getTranslations'])->where('locale', 'ar|en')->name('lang.translations');

// ── Subscribe (auth + tenancy, no subscription-check to avoid loop) ──────
Route::middleware(['auth', 'tenancy'])->group(function () {
    Route::get('/subscribe', function () {
        $tenant = tenancy()->tenant;
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $methods = PaymentAccount::configured();
        $waAccount = PaymentAccount::where('method', 'whatsapp')->where('is_active', true)->first();
        $whatsapp = $waAccount?->account_number ?? '201000000000';

        return view('subscription.subscribe', compact('tenant', 'plans', 'methods', 'whatsapp'));
    })->name('subscribe');
});

// ── Impersonate leave — only auth+tenancy; no 2FA/subscription gate needed ──
Route::middleware(['auth', 'tenancy'])->post('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');

// ── Authenticated web views ───────────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class])->group(function () {
    Route::get('/session-info', [AuthController::class, 'sessionInfo'])->name('session.info');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/low-stock', [DashboardController::class, 'lowStock'])->name('dashboard.low-stock');
    Route::middleware(['permission:view_pos'])->group(function () {
        Route::get('/pos', [InvoiceController::class, 'posPage'])->name('pos');
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses');
    });
    Route::middleware(['permission:view_returns'])->get('/returns', fn () => view('returns.index'))->name('returns');
    Route::middleware(['permission:view_warehouse'])->group(function () {
        Route::get('/warehouse', fn () => view('warehouse.index'))->name('warehouse');
        Route::get('/warehouses', [WarehouseController::class, 'page'])->name('warehouses');
        Route::get('/waste', [WasteController::class, 'index'])->name('waste');
        Route::get('/suppliers', fn () => view('suppliers.index'))->name('suppliers');
        // ── Product Import / Export ───────────────────────────────────────────
        Route::get('/products/import/template', [App\Http\Controllers\ProductImportController::class, 'template'])->name('products.import.template');
        Route::post('/products/import', [App\Http\Controllers\ProductImportController::class, 'import'])->name('products.import');
        Route::get('/products/export', [App\Http\Controllers\ProductImportController::class, 'export'])->name('products.export');
        Route::get('/purchase-orders', fn () => view('purchase-orders.index'))->name('purchase-orders');
        Route::get('/supplier-payments', fn () => view('supplier-payments.index'))->name('supplier-payments');
        Route::get('/supplier-accounts', fn () => view('supplier-accounts.index'))->name('supplier-accounts');
        Route::get('/purchase-returns', [PurchaseReturnController::class, 'index'])->name('purchase-returns');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers');
        Route::get('/customer-groups', fn () => view('customer-groups.index'))->name('customer-groups');
        Route::get('/promotions', fn () => view('promotions.index'))->name('promotions');
    });
    Route::middleware(['permission:view_accounting'])->group(function () {
        Route::get('/accounting', fn () => view('accounting.index'))->name('accounting');
        Route::get('/financial-reports', fn () => view('financial-reports.index'))->name('financial-reports');
        Route::get('/settings', fn () => view('settings.index'))->name('settings');
    });
    Route::middleware(['permission:view_reports'])->group(function () {
        Route::get('/reports', fn () => view('reports.index'))->name('reports');
        Route::get('/reports/budget', fn () => view('reports.budget'))->name('reports.budget');
        Route::middleware('throttle:10,1')->group(function () {
            Route::get('/reports/export/sales', [ReportController::class, 'exportSales'])->name('reports.export.sales');
            Route::get('/reports/export/returns', [ReportController::class, 'exportReturns'])->name('reports.export.returns');
            Route::get('/reports/export/stock', [ReportController::class, 'exportStock'])->name('reports.export.stock');
        });
    });
    Route::middleware(['permission:manage_roles'])->get('/roles', fn () => view('roles.index'))->name('roles');
    Route::middleware(['permission:manage_roles'])->get('/branches', [BranchController::class, 'page'])->name('branches');
    Route::middleware(['permission:manage_roles'])->get('/whatsapp', fn () => view('whatsapp.index'))->name('whatsapp');

    // ── Impersonation ─────────────────────────────────────────────────────
    Route::middleware(['permission:manage_roles'])->group(function () {
        Route::post('/impersonate/{user}', [ImpersonateController::class, 'start'])->name('impersonate.start');
    });

    // ── Tenant management (master-tenant admin only) ───────────────────────
    Route::middleware(['permission:manage_tenants'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/cpanel', [TenantController::class, 'cpanel'])->name('cpanel');
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants');
        Route::get('/tenants/stats', [TenantController::class, 'stats'])->name('tenants.stats');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::put('/tenants/{id}', [TenantController::class, 'update'])->name('tenants.update');
        Route::patch('/tenants/{id}/toggle', [TenantController::class, 'toggle'])->name('tenants.toggle');
        Route::delete('/tenants/{id}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::post('/tenants/{id}/seed', [TenantController::class, 'seed'])->name('tenants.seed');
        Route::post('/tenants/{id}/extend', [TenantController::class, 'extend'])->name('tenants.extend');
        Route::patch('/tenants/{id}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::patch('/tenants/{id}/cancel', [TenantController::class, 'cancelSubscription'])->name('tenants.cancel');
        Route::get('/tenants/{id}/users', [TenantController::class, 'tenantUsers'])->name('tenants.users');
        Route::patch('/tenants/{id}/users/{userId}/toggle', [TenantController::class, 'toggleTenantUser'])->name('tenants.users.toggle');

        // ── Plans & Pricing ───────────────────────────────────────────────
        Route::get('/plans', [PlanController::class, 'index'])->name('plans');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{id}', [PlanController::class, 'update'])->name('plans.update');
        Route::patch('/plans/{id}/toggle', [PlanController::class, 'toggle'])->name('plans.toggle');
        Route::delete('/plans/{id}', [PlanController::class, 'destroy'])->name('plans.destroy');

        // ── Payment Accounts (wallet numbers) ─────────────────────────────
        Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])->name('payment-accounts.index');
        Route::get('/payment-accounts/page', [PaymentAccountController::class, 'page'])->name('payment-accounts.page');
        Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->name('payment-accounts.update');
    });
});

// تسوية الخزينة
Route::middleware(['auth', '2fa', 'permission:view_pos'])->group(function () {
    Route::get('/cash-register', fn () => view('cash-register.index'))->name('cash-register');
});

Route::middleware(['auth', '2fa', 'permission:view_reports'])->group(function () {
    Route::get('/profit-reports', fn () => view('profit-reports.index'))->name('profit-reports');
});

// ── Kitchen Display System ────────────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class])->group(function () {
    Route::middleware(['permission:view_pos'])->group(function () {
        Route::get('/kitchen', [KitchenDisplayController::class, 'index'])->name('kitchen');
        Route::get('/kitchen/display', [KitchenDisplayController::class, 'display'])->name('kitchen.display');
    });
    // CRM
    Route::middleware(['permission:view_warehouse'])->group(function () {
        Route::get('/crm', [CrmController::class, 'index'])->name('crm');
        Route::get('/crm/customer/{id}', [CrmController::class, 'customer'])->name('crm.customer');
    });
    // Dynamic Pricing
    Route::middleware(['permission:view_pos'])->group(function () {
        Route::get('/pricing-rules', [DynamicPricingController::class, 'index'])->name('pricing-rules');
    });
    // Forecasting
    Route::middleware(['permission:view_reports'])->group(function () {
        Route::get('/forecasting', [ForecastController::class, 'index'])->name('forecasting');
    });
    // Device Sessions
    Route::middleware(['auth'])->group(function () {
        Route::get('/device-sessions', [DeviceSessionController::class, 'index'])->name('device-sessions');
        Route::delete('/device-sessions/{id}', [DeviceSessionController::class, 'revoke'])->name('device-sessions.revoke');
        Route::delete('/device-sessions', [DeviceSessionController::class, 'revokeAll'])->name('device-sessions.revoke-all');
    });
    // Cashback Management
    Route::middleware(['permission:view_warehouse'])->group(function () {
        Route::get('/cashback', [CashbackController::class, 'indexPage'])->name('cashback');
    });
});

// ── QR Ordering (public, no auth) ─────────────────────────────────────────
Route::prefix('qr')->name('qr.')->group(function () {
    Route::get('/{token}', [QrOrderController::class, 'menu'])->name('menu');
    Route::post('/{token}/order', [QrOrderController::class, 'placeOrder'])->name('order')->middleware('throttle:10,1');
    Route::get('/{token}/order/{orderId}/status', [QrOrderController::class, 'status'])->name('status');
});

// ── QR Management (admin) ─────────────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class, 'permission:view_pos'])->group(function () {
    Route::get('/qr-tables', [QrOrderController::class, 'manage'])->name('qr-tables');
    Route::post('/qr-tables', [QrOrderController::class, 'generate'])->name('qr-tables.generate');
});

// ── Phase 11: Kiosk (public self-service, no auth required) ──────────────
Route::middleware(['tenancy', 'throttle:30,1'])->group(function () {
    Route::get('/kiosk', [App\Http\Controllers\KioskController::class, 'index'])->name('kiosk');
});

// ── Phase 2: Shift Management ─────────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class, 'planFeature:shift_management'])->group(function () {
    Route::get('/shifts', [App\Http\Controllers\ShiftController::class, 'index'])
        ->middleware('permission:view_shifts')->name('shifts.index');
    Route::get('/my-shift', [App\Http\Controllers\ShiftController::class, 'myShift'])->name('shifts.my');
});

// ── Phase 4: White Label Settings ─────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class, 'planFeature:white_label', 'permission:manage_white_label'])->group(function () {
    Route::get('/white-label', [App\Http\Controllers\WhiteLabelController::class, 'index'])->name('white-label');
    Route::get('/white-label/css', [App\Http\Controllers\WhiteLabelController::class, 'cssVars'])->name('white-label.css');
});

// ── Phase 10: HR Module ────────────────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class, 'planFeature:hr_module'])->group(function () {
    // Attendance & schedule: anyone with view_hr or manage_hr
    Route::get('/hr/attendance', function () {
        /** @var App\Models\User $u */
        $u = auth()->user();
        abort_unless($u->hasAnyPermission(['view_hr', 'manage_hr', 'manage_settings']), 403);
        $branches = App\Models\Branch::orderBy('name')->get();

        return view('hr.attendance', compact('branches'));
    })->name('hr.attendance');

    // Payroll: manage_hr or manage_settings only
    Route::get('/hr/payroll', function () {
        /** @var App\Models\User $u */
        $u = auth()->user();
        abort_unless($u->hasAnyPermission(['manage_hr', 'manage_settings']), 403);
        $branches = App\Models\Branch::orderBy('name')->get();

        return view('hr.payroll', compact('branches'));
    })->name('hr.payroll');

    Route::get('/hr/leaves', function () {
        /** @var App\Models\User $u */
        $u = auth()->user();
        abort_unless($u->hasAnyPermission(['view_hr', 'manage_hr', 'manage_settings']), 403);

        return view('hr.leaves');
    })->name('hr.leaves');

    // Employees — manage salaries and leave allocations
    Route::get('/hr/employees', function () {
        /** @var App\Models\User $u */
        $u = auth()->user();
        abort_unless($u->hasAnyPermission(['view_hr', 'manage_hr', 'manage_settings']), 403);
        $branches = App\Models\Branch::orderBy('name')->get();

        return view('hr.employees', compact('branches'));
    })->name('hr.employees');
});

// ── Phase 10: Multi-Currency ───────────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class, 'planFeature:currencies', 'permission:manage_currencies'])->group(function () {
    Route::get('/currencies', fn () => view('currencies.index'))->name('currencies.index');
});

// ── Phase 10: Franchise Royalties ──────────────────────────────────────────
Route::middleware(['auth', 'tenancy', '2fa', CheckSubscriptionActive::class, 'planFeature:franchise', 'permission:view_franchise'])->group(function () {
    Route::get('/franchise/royalties', fn () => view('franchise.royalties'))->name('franchise.royalties');
});
