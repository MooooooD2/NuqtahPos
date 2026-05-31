# نظام نقطة البيع — POS Laravel

## متطلبات التشغيل
- PHP >= 8.2
- Laravel 11
- MySQL 8+
- Composer

## التثبيت
```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

## بعد التثبيت (مهم)
1. غيّر كلمات المرور في `.env` قبل النشر
2. تأكد `APP_DEBUG=false` في الإنتاج
3. شغّل `php artisan config:cache` و `php artisan route:cache`

## هيكل المشروع
```
app/
├── Http/
│   ├── Controllers/    # Controllers خفيفة (Thin)
│   ├── Requests/       # Form Requests لكل endpoint
│   ├── Resources/      # API Resources لتنسيق الردود
│   └── Middleware/     # SecurityHeaders, SetLocale
├── Models/             # Eloquent Models مع fillable صريح
├── Policies/           # Authorization Policies
├── Services/           # Business Logic
│   ├── InvoiceService  # الفواتير
│   ├── StockService    # المخزون + Audit
│   ├── ReturnService   # المرتجعات
│   └── PurchaseOrderService
└── Traits/
    ├── ApiResponse     # ردود API موحدة
    └── AuditLog        # تسجيل العمليات
routes/
├── web.php             # صفحات HTML
└── api.php             # API endpoints
```

## الأمان المُطبَّق
- ✅ Rate Limiting: 5 محاولات/دقيقة للـ Login، 60/دقيقة للـ APIs
- ✅ is_active فحص عند كل تسجيل دخول
- ✅ Form Requests لكل endpoint
- ✅ Policies لكل Model
- ✅ Security Headers (CSP, XSS Protection, etc.)
- ✅ أسعار الفواتير من DB فقط
- ✅ lockForUpdate لمنع Race Conditions
- ✅ API Resources لمنع تسريب البيانات
- ✅ Audit Trail في `storage/logs/audit.log`

## تشغيل الاختبارات
```bash
php artisan test
```

## الصلاحيات
| الدور    | الصلاحيات                    |
|---------|------------------------------|
| admin   | كل شيء                       |
| cashier | view_pos, view_returns       |
| warehouse | view_warehouse, add_stock  |
| accountant | view_accounting, view_reports |
