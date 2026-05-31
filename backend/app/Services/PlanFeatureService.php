<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Resolves whether the current tenant's plan grants access to a named feature.
 *
 * Feature flags are stored as a JSON array on the plans table (`feature_flags`).
 * When that column is absent we fall back to tier-based defaults so the system
 * degrades gracefully.
 *
 * Usage:
 *   PlanFeatureService::has('hr_module')     // bool
 *   PlanFeatureService::check('crm')         // throws 403 if missing
 *   PlanFeatureService::allModules()         // full catalog with labels + icons
 *
 * Blade:
 *
 *   @planFeature('hr_module') … @endplanFeature
 */
class PlanFeatureService
{
    /**
     * Default feature flags per plan tier (fallback when DB has no feature_flags).
     */
    private const PLAN_DEFAULTS = [
        'basic' => [
            'pos', 'inventory', 'returns', 'expenses', 'reports_basic', 'customers',
        ],
        'pro' => [
            'pos', 'inventory', 'returns', 'expenses',
            'reports_basic', 'reports_advanced',
            'customers', 'customer_groups', 'promotions', 'cashback',
            'accounting', 'purchase_orders', 'multi_warehouse',
            'whatsapp', 'kitchen_display', 'qr_ordering', 'kiosk',
            'crm', 'dynamic_pricing', 'pricing_rules', 'waste_tracking',
        ],
        'enterprise' => [
            'pos', 'inventory', 'returns', 'expenses',
            'reports_basic', 'reports_advanced', 'reports_financial',
            'customers', 'customer_groups', 'promotions', 'cashback',
            'accounting', 'purchase_orders', 'multi_warehouse', 'multi_branch',
            'whatsapp', 'kitchen_display', 'qr_ordering', 'kiosk',
            'crm', 'dynamic_pricing', 'pricing_rules', 'waste_tracking',
            'hr_module', 'payroll', 'shift_management',
            'white_label', 'currencies', 'franchise',
            'ai_forecasting', 'budget_vs_actual',
            'device_sessions', 'backup_monitor',
        ],
    ];

    /**
     * Full catalogue of available modules — used in the plan admin UI and on pricing cards.
     *
     * Each entry: [ key => [ar, en, icon, group] ]
     * Icons are Font Awesome free class names (without the leading `fas `).
     */
    public static function allModules(): array
    {
        return [
            // ── Sales & Operations ────────────────────────────────────────────
            'pos' => ['ar' => 'نقطة البيع',              'en' => 'Point of Sale',       'icon' => 'fa-cash-register',          'group' => 'sales'],
            'returns' => ['ar' => 'المرتجعات',               'en' => 'Returns',              'icon' => 'fa-rotate-left',            'group' => 'sales'],
            'expenses' => ['ar' => 'المصروفات',               'en' => 'Expenses',             'icon' => 'fa-receipt',                'group' => 'sales'],
            'kitchen_display' => ['ar' => 'شاشة المطبخ',            'en' => 'Kitchen Display',      'icon' => 'fa-utensils',               'group' => 'sales'],
            'qr_ordering' => ['ar' => 'طلبات QR',               'en' => 'QR Ordering',          'icon' => 'fa-qrcode',                 'group' => 'sales'],
            'kiosk' => ['ar' => 'وضع الكشك',              'en' => 'Kiosk Mode',           'icon' => 'fa-tablet-screen-button',   'group' => 'sales'],
            'shift_management' => ['ar' => 'إدارة الورديات',          'en' => 'Shift Management',     'icon' => 'fa-user-clock',             'group' => 'sales'],

            // ── Inventory & Supply ────────────────────────────────────────────
            'inventory' => ['ar' => 'إدارة المخزون',           'en' => 'Inventory',            'icon' => 'fa-boxes-stacked',          'group' => 'inventory'],
            'purchase_orders' => ['ar' => 'طلبات الشراء',           'en' => 'Purchase Orders',      'icon' => 'fa-file-invoice-dollar',    'group' => 'inventory'],
            'multi_warehouse' => ['ar' => 'مستودعات متعددة',        'en' => 'Multi-Warehouse',      'icon' => 'fa-warehouse',              'group' => 'inventory'],
            'waste_tracking' => ['ar' => 'تتبع الهدر',             'en' => 'Waste Tracking',       'icon' => 'fa-trash-alt',              'group' => 'inventory'],
            'pricing_rules' => ['ar' => 'قواعد التسعير',          'en' => 'Pricing Rules',        'icon' => 'fa-tags',                   'group' => 'inventory'],
            'dynamic_pricing' => ['ar' => 'تسعير ديناميكي',         'en' => 'Dynamic Pricing',      'icon' => 'fa-sliders',                'group' => 'inventory'],

            // ── Customers & Marketing ─────────────────────────────────────────
            'customers' => ['ar' => 'إدارة العملاء',          'en' => 'Customers',            'icon' => 'fa-users',                  'group' => 'customers'],
            'customer_groups' => ['ar' => 'مجموعات العملاء',        'en' => 'Customer Groups',      'icon' => 'fa-people-group',           'group' => 'customers'],
            'promotions' => ['ar' => 'العروض الترويجية',       'en' => 'Promotions',           'icon' => 'fa-percent',                'group' => 'customers'],
            'cashback' => ['ar' => 'الكاش باك',              'en' => 'Cashback',             'icon' => 'fa-coins',                  'group' => 'customers'],
            'crm' => ['ar' => 'إدارة علاقات العملاء',  'en' => 'CRM',                  'icon' => 'fa-users-gear',             'group' => 'customers'],
            'whatsapp' => ['ar' => 'تسويق واتساب',           'en' => 'WhatsApp',             'icon' => 'fa-comment-dots',           'group' => 'customers'],

            // ── Finance & Accounting ──────────────────────────────────────────
            'accounting' => ['ar' => 'المحاسبة',               'en' => 'Accounting',           'icon' => 'fa-book-open',              'group' => 'finance'],
            'reports_basic' => ['ar' => 'تقارير أساسية',         'en' => 'Basic Reports',        'icon' => 'fa-chart-column',           'group' => 'finance'],
            'reports_advanced' => ['ar' => 'تقارير متقدمة',         'en' => 'Advanced Reports',     'icon' => 'fa-chart-line',             'group' => 'finance'],
            'reports_financial' => ['ar' => 'تقارير مالية',          'en' => 'Financial Reports',    'icon' => 'fa-chart-area',             'group' => 'finance'],
            'budget_vs_actual' => ['ar' => 'الميزانية مقابل الفعلي', 'en' => 'Budget vs Actual',     'icon' => 'fa-scale-unbalanced',       'group' => 'finance'],
            'ai_forecasting' => ['ar' => 'تنبؤ بالذكاء الاصطناعي', 'en' => 'AI Forecasting',      'icon' => 'fa-robot',                  'group' => 'finance'],

            // ── HR ────────────────────────────────────────────────────────────
            'hr_module' => ['ar' => 'الموارد البشرية',        'en' => 'HR Module',            'icon' => 'fa-id-badge',               'group' => 'hr'],
            'payroll' => ['ar' => 'الرواتب',                'en' => 'Payroll',              'icon' => 'fa-money-check-dollar',     'group' => 'hr'],

            // ── Enterprise / Advanced ─────────────────────────────────────────
            'multi_branch' => ['ar' => 'فروع متعددة',            'en' => 'Multi-Branch',         'icon' => 'fa-sitemap',                'group' => 'enterprise'],
            'white_label' => ['ar' => 'العلامة البيضاء',       'en' => 'White Label',          'icon' => 'fa-palette',                'group' => 'enterprise'],
            'currencies' => ['ar' => 'عملات متعددة',          'en' => 'Multi-Currency',       'icon' => 'fa-coins',                  'group' => 'enterprise'],
            'franchise' => ['ar' => 'الامتياز التجاري',      'en' => 'Franchise',            'icon' => 'fa-handshake',              'group' => 'enterprise'],
            'device_sessions' => ['ar' => 'جلسات الأجهزة',         'en' => 'Device Sessions',      'icon' => 'fa-laptop-mobile',          'group' => 'enterprise'],
            'backup_monitor' => ['ar' => 'مراقبة النسخ الاحتياطي', 'en' => 'Backup Monitor',       'icon' => 'fa-server',                 'group' => 'enterprise'],
        ];
    }

    /**
     * Group labels for display.
     */
    public static function moduleGroups(): array
    {
        return [
            'sales' => ['ar' => 'المبيعات والعمليات',        'en' => 'Sales & Operations'],
            'inventory' => ['ar' => 'المخزون والتوريد',          'en' => 'Inventory & Supply'],
            'customers' => ['ar' => 'العملاء والتسويق',          'en' => 'Customers & Marketing'],
            'finance' => ['ar' => 'المالية والتقارير',          'en' => 'Finance & Reports'],
            'hr' => ['ar' => 'الموارد البشرية',           'en' => 'Human Resources'],
            'enterprise' => ['ar' => 'ميزات المؤسسات',           'en' => 'Enterprise Features'],
        ];
    }

    /**
     * Returns the set of features enabled for the current tenant's plan.
     */
    public static function features(): array
    {
        $tenant = tenancy()->tenant;

        // Master tenant always has all features
        $masterId = config('tenancy.master_tenant');
        if ($masterId && $tenant?->id === $masterId) {
            return array_keys(self::allModules());
        }

        $planId = $tenant?->plan ?? 'basic';

        return Cache::remember("plan_features:{$planId}", 3600, function () use ($planId) {
            try {
                $plan = Plan::find($planId);
                if ($plan && ! empty($plan->feature_flags)) {
                    return (array) $plan->feature_flags;
                }
            } catch (Throwable) {
                // Column doesn't exist yet — use defaults
            }

            return self::PLAN_DEFAULTS[$planId] ?? self::PLAN_DEFAULTS['basic'];
        });
    }

    /**
     * Check whether a single feature is enabled.
     */
    public static function has(string $feature): bool
    {
        return in_array($feature, self::features(), true);
    }

    /**
     * Abort with 403 when the feature is not in the plan.
     */
    public static function check(string $feature): void
    {
        if (! static::has($feature)) {
            $message = __('pos.feature_not_in_plan', ['feature' => $feature]);
            if (request()->expectsJson() || request()->is('api/*')) {
                abort(response()->json(['success' => false, 'message' => $message, 'upgrade_required' => true], 403));
            }
            abort(403, $message);
        }
    }
}
