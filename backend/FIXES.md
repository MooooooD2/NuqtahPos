# سجل الإصلاحات الأمنية — POS Laravel

## ملخص التغييرات

| # | الثغرة | الملفات المعدّلة | الخطورة |
|---|--------|-----------------|---------|
| FIX-1 | SESSION_ENCRYPT=false | `.env.example` | متوسطة |
| FIX-2 | خصم بلا حد أقصى | `StoreInvoiceRequest`, `InvoiceService`, `UserSeeder`, migration | عالية |
| FIX-3 | ReturnController بلا authorize() | `ReturnController`, `AppServiceProvider` | متوسطة |
| FIX-4 | auditTrail بلا تحقق من وجود المنتج | `StockReconciliationController` | منخفضة |
| FIX-5 | SequenceService يفشل صامتاً | `SequenceService` | منخفضة |
| FIX-6 | Session timeout غير متزامن | `SessionSecurity` | منخفضة |
| FIX-7 | CSP يسمح بـ unsafe-inline للـ scripts | `SecurityHeaders` | متوسطة |
| FIX-8 | AnomalyDetection لا تراقب الخصم | `AnomalyDetection` | منخفضة |
| FIX-9 | clear-cache.php بـ secret مُشفّر في الكود | `public/clear-cache.php` (محذوف) | عالية |
| FIX-10 | Tap webhook يُفعّل اشتراكات بلا توثيق | `TapController` | حرجة |
| FIX-11 | WHATSAPP_VERIFY_TOKEN قيمة افتراضية ضعيفة | `.env.example` | متوسطة |

---

## تفاصيل كل إصلاح

### FIX-1: تشفير الجلسات
**المشكلة:** `SESSION_ENCRYPT=false` — الجلسات مخزنة كـ plaintext في قاعدة البيانات.

**الإصلاح:** تغيير القيمة الافتراضية إلى `SESSION_ENCRYPT=true` في `.env.example`.

**ملاحظة:** يجب تحديث ملف `.env` الفعلي في الخادم بنفس القيمة.

```env
SESSION_ENCRYPT=true
```

---

### FIX-2: حد الخصم الأقصى
**المشكلة:** أي كاشير يمكنه تطبيق خصم 100% على أي فاتورة بدون قيود — خسارة مالية مباشرة.

**الإصلاح:**
1. إضافة `MAX_DISCOUNT_PERCENT=20` في `.env.example`
2. قراءة الحد من `Setting::get('max_discount_percent')` في `InvoiceService`
3. إضافة migration يُدخل الإعداد في قاعدة البيانات
4. `UserSeeder` يرفض الـ seed إذا كانت كلمات المرور فارغة

**كيفية تغيير الحد:**
- من `.env`: `MAX_DISCOUNT_PERCENT=30`
- أو من واجهة الإعدادات في النظام (settings table)

---

### FIX-3: Authorization في ReturnController
**المشكلة:** `ReturnController::store()` لم يكن يستدعي `$this->authorize()` داخلياً — الاعتماد فقط على route middleware.

**الإصلاح:**
1. إضافة `$this->authorize('create', SalesReturn::class)` في الـ controller
2. تسجيل جميع الـ Policies صراحةً في `AppServiceProvider::$policies`

---

### FIX-4: auditTrail بلا تحقق
**المشكلة:** `/api/stock/audit-trail/{productId}` يقبل أي رقم كـ productId حتى لو المنتج غير موجود — يُرجع خطأ DB غير معالج.

**الإصلاح:** استخدام `Product::findOrFail($productId)` قبل استدعاء الـ service — يُرجع 404 نظيفاً.

---

### FIX-5: SequenceService الصامت
**المشكلة:** عند عدم وجود الـ sequence، الكود يُعيد `id=1` دائماً بدون تسجيل أي خطأ — فواتير بنفس الرقم ممكنة.

**الإصلاح:** إضافة `Log::warning()` عند الـ fallback + إعادة قراءة القيمة الفعلية من DB بعد الإنشاء.

---

### FIX-6: Session Timeout غير متزامن
**المشكلة:** `SessionSecurity` كانت تستخدم `8 * 3600` ثابتاً بينما `.env` يحدد `SESSION_LIFETIME=120` دقيقة — تعارض يُربك المستخدمين.

**الإصلاح:** استخدام `config('session.lifetime') * 60` بدلاً من القيمة الثابتة.

```env
# الآن SESSION_LIFETIME=480 (8 ساعات) في .env.example
SESSION_LIFETIME=480
```

---

### FIX-7: CSP و unsafe-inline
**المشكلة:** `script-src 'unsafe-inline'` يُبطل فعالية CSP في منع XSS.

**الإصلاح:** إزالة `unsafe-inline` من `script-src` فقط. `style-src` لا تزال تحتوي عليه لأن إزالتها تتطلب نقل كل الـ inline styles لملفات منفصلة (تحسين مستقبلي).

---

### FIX-8: مراقبة محاولات تجاوز الخصم
**المشكلة:** محاولات تجاوز حد الخصم لم تكن مرصودة.

**الإصلاح:** إضافة `detectDiscountCapViolation()` في `AnomalyDetection` — تُسجّل تحذيراً بعد 3 محاولات متكررة.

---

## خطوات التطبيق

```bash
# 1. تحديث .env
SESSION_ENCRYPT=true
SESSION_LIFETIME=480
MAX_DISCOUNT_PERCENT=20
ADMIN_PASSWORD=YourStrongPassword@123
CASHIER_PASSWORD=YourStrongPassword@456
WAREHOUSE_PASSWORD=YourStrongPassword@789

# 2. تشغيل الـ migration الجديد
php artisan migrate

# 3. تحديث كاش الصلاحيات
php artisan permission:cache-reset

# 4. تشغيل الاختبارات
php artisan test

# 5. مسح الكاش
php artisan cache:clear
php artisan config:clear
```

---

---

### FIX-9: حذف clear-cache.php
**المشكلة:** `public/clear-cache.php` كان يحتوي على secret ثابت مكتوب في الكود (`biskumarket-clear-2026`). أي شخص يكتشف الملف والـ secret يمكنه مسح كاش الـ routes والـ config في الإنتاج.

**الإصلاح:** حذف الملف نهائياً. استخدم `php artisan cache:clear && php artisan config:clear` مباشرة على السيرفر.

---

### FIX-10: Tap Webhook بلا توثيق
**المشكلة:** `TapController::webhook()` كان يُفعّل الاشتراكات بناءً على محتوى الـ request مباشرة — أي شخص يستطيع إرسال POST مزيف وتفعيل اشتراك مجاناً.

**الإصلاح:** بدلاً من الثقة في payload الـ webhook، يتم الآن التحقق من حالة الـ charge مباشرةً من Tap API باستخدام `charge_id`. هذا يضمن أن التفعيل لا يحدث إلا إذا كانت المدفوعة مؤكدة فعلاً من Tap.

---

### FIX-11: WHATSAPP_VERIFY_TOKEN قيمة افتراضية ضعيفة
**المشكلة:** `.env.example` كان يحتوي على `WHATSAPP_VERIFY_TOKEN=my_secure_verify_token` — قيمة يمكن تخمينها.

**الإصلاح:** القيمة الآن فارغة مع تعليق يُلزم المطور بتعيينها قبل النشر.

---

---

### FIX-12: نقاط ضعف متعددة (جلسة التقييم)
**المشاكل التي تم إصلاحها:**
- `DashboardService::lowStockAlerts()` كانت تُرجع بيانات وهمية (أصفار مكتوبة يدوياً)
- `ImpersonateController` لا يمنع انتحال صفة Admin آخر ← ثغرة Privilege Escalation
- `totalRevenue()` و`totalSuppliers()` يسكانان جدول الفواتير الكامل بلا cache
- `_all_controllers.php`, `_all_services.php`, `_all_models.php` ملفات dev في الـ production code
- `recentMovements()` يجلب كل الأعمدة بلا `select` محدد
- `AnomalyDetection` يُسجّل فقط ولا يحجب تلقائياً
- لا cache على `DashboardService::getData()` (7-8 queries لكل load)

**الإصلاحات:**
1. `lowStockAlerts()` الآن تستخدم `ProductRepository::outOfStock()` + `lowStock()` فعلياً
2. `ImpersonateController::start()` يرفض انتحال صفة أي مستخدم لديه `manage_roles` + يُسجّل في audit log
3. `totalRevenue()` + `totalSuppliers()` مُكاشَتان 10 دقائق مع invalidation عند إنشاء/إلغاء فاتورة
4. الملفات الثلاثة محذوفة
5. `recentMovements()` يحدد الأعمدة بـ `select(['id','product_id','movement_type','quantity','reason','created_at'])`
6. `AnomalyDetection` تُصدر **حجب مؤقت 5 دقائق** بعد 5 ضربات anomaly متراكمة (rapid requests + discount violations)
7. `DashboardService::getData()` مُكاشة 60 ثانية + `forgetCache()` تُستدعى عند كل فاتورة جديدة/ملغاة
8. **Unit tests لـ AccountingService**: 10 اختبارات تغطي journal balance، fiscal period locking، posting immutability، reversal

---

### FIX-13: تقليل inline styles (مرحلة 1 من 3)
**المشكلة:** `style-src-attr 'unsafe-inline'` في CSP يسمح بـ CSS injection عبر `style=""` attributes في HTML.

**تقرير الأوضاع (من `php artisan csp:audit-inline-styles`):**
- 347 inline style attribute عبر 36 ملف قبل الإصلاح
- **بعد المرحلة 1:** 331 عبر 23 ملف (13 ملف مُنظّف بالكامل)

**ما تم:**
- أضفنا utility classes في `public/css/styles.css`: `.u-hidden`, `.u-hidden-imp`, `.u-mw-200/260/480`, `.u-cursor-pointer`, `.u-font-cairo`, `.u-font-sm`, `.u-empty-state`
- نظّفنا 13 ملف: `financial-reports/`, `settings/`, `warehouses/`, `auth/2fa/`, `auth/login`, `expenses/`, `promotions/`, `reports/pdf/`, `roles/`, `supplier-payments/`, `warehouse/`
- أضفنا `app/Console/Commands/AuditInlineStylesCommand.php` (`php artisan csp:audit-inline-styles`) لتتبع التقدم

**الخطوات التالية (مرحلة 2 + 3):**
```
المرحلة 2 (3-10 occurrences) — 10 ملفات:
  layouts/app.blade.php (6), reports/budget.blade.php (6),
  accounting/index.blade.php (4), auth/register.blade.php (4),
  subscription/success.blade.php (4), cash-register/ (3), etc.

المرحلة 3 (كبار) — الأصعب:
  pos/index.blade.php        → 102 occurrences (POS الرئيسي)
  welcome.blade.php          → 42  occurrences
  reports/index.blade.php    → 37  occurrences
  returns/index.blade.php    → 24  occurrences

عند اكتمال المرحلة 3: تغيير SecurityHeaders:
  "style-src-attr 'unsafe-inline';"  →  "style-src-attr 'none';"
```

---

## ما تبقى (تحسينات مستقبلية)

| التحسين | الأولوية | الوصف |
|---------|----------|-------|
| 2FA للـ Admin | عالية | إضافة TOTP للمدير فقط في المرحلة الأولى |
| إزالة unsafe-inline من CSS (مرحلة 2+3) | عالية | 331 inline style متبقية في 23 ملف — تتبع التقدم بـ `php artisan csp:audit-inline-styles` |
| نسخ احتياطية مشفرة | عالية | تعيين `BACKUP_ARCHIVE_PASSWORD` في `.env` وتشغيل `php artisan backup:run` |
| Redis للـ Cache | منخفضة | عند زيادة المستخدمين — يحل محل DB cache |

