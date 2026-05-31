<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  @font-face {
    font-family: 'DejaVu Sans';
    font-style: normal;
    font-weight: normal;
    src: url('{{ storage_path("fonts/DejaVuSans.ttf") }}');
  }

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    font-family: 'DejaVu Sans', Arial, sans-serif;
    font-size: 11px;
    color: #1e293b;
    background: #fff;
    direction: rtl;
    text-align: right;
  }

  /* ── Cover ── */
  .cover {
    background: linear-gradient(135deg, #12244E 0%, #1a3a6b 100%);
    color: #fff;
    padding: 60px 50px;
    text-align: center;
    min-height: 200px;
    border-bottom: 6px solid #00B04E;
  }
  .cover h1 { font-size: 24px; font-weight: bold; margin-bottom: 8px; letter-spacing: 1px; }
  .cover h2 { font-size: 15px; font-weight: normal; opacity: .85; margin-bottom: 20px; }
  .cover .score-box {
    display: inline-block;
    background: #00B04E;
    color: #fff;
    font-size: 36px;
    font-weight: bold;
    padding: 14px 36px;
    border-radius: 12px;
    margin: 12px 0;
    letter-spacing: 2px;
  }
  .cover .meta { font-size: 10px; opacity: .7; margin-top: 14px; }

  /* ── Section ── */
  .section { padding: 22px 40px; border-bottom: 1px solid #e2e8f0; }
  .section:last-child { border-bottom: none; }

  .section-title {
    font-size: 14px;
    font-weight: bold;
    color: #12244E;
    border-right: 4px solid #00B04E;
    padding-right: 10px;
    margin-bottom: 14px;
  }

  /* ── Score summary table ── */
  .score-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .score-table th {
    background: #12244E;
    color: #fff;
    padding: 7px 10px;
    font-size: 10.5px;
    text-align: right;
  }
  .score-table td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; }
  .score-table tr:nth-child(even) td { background: #f8fafc; }

  .bar-wrap { width: 100px; background: #e2e8f0; border-radius: 4px; height: 10px; display: inline-block; vertical-align: middle; }
  .bar-fill  { height: 10px; border-radius: 4px; display: inline-block; }

  .badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 9.5px;
    font-weight: bold;
    color: #fff;
  }
  .badge-green  { background: #00B04E; }
  .badge-blue   { background: #3b82f6; }
  .badge-orange { background: #f59e0b; }
  .badge-red    { background: #ef4444; }

  /* ── Domain detail card ── */
  .domain-card {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 14px;
    overflow: hidden;
  }
  .domain-header {
    background: #f1f5f9;
    padding: 8px 14px;
    display: table;
    width: 100%;
  }
  .domain-header .dh-title { display: table-cell; font-weight: bold; font-size: 12px; color: #12244E; }
  .domain-header .dh-score { display: table-cell; text-align: left; font-size: 20px; font-weight: bold; color: #00B04E; white-space: nowrap; }
  .domain-header .dh-change { display: table-cell; text-align: center; font-size: 9.5px; white-space: nowrap; padding: 0 8px; }

  .domain-body { padding: 10px 14px; }

  ul.checklist { list-style: none; padding: 0; margin: 0; }
  ul.checklist li { padding: 2px 0; font-size: 10px; }
  ul.checklist li::before { content: "✓  "; color: #00B04E; font-weight: bold; }
  ul.checklist li.warn::before { content: "⚠  "; color: #f59e0b; }
  ul.checklist li.na::before   { content: "–  "; color: #94a3b8; }

  /* ── Two-column ── */
  .two-col { display: table; width: 100%; }
  .col { display: table-cell; width: 50%; vertical-align: top; padding: 0 6px; }
  .col:first-child { padding-right: 0; }
  .col:last-child  { padding-left: 0; }

  /* ── Fixes timeline ── */
  .fix-row { display: table; width: 100%; margin-bottom: 6px; }
  .fix-num  { display: table-cell; width: 30px; font-weight: bold; color: #12244E; white-space: nowrap; }
  .fix-body { display: table-cell; }
  .fix-title { font-weight: bold; font-size: 10.5px; }
  .fix-desc  { font-size: 9.5px; color: #475569; }

  /* ── Roadmap ── */
  .road-table { width: 100%; border-collapse: collapse; }
  .road-table th { background: #1a3a6b; color: #fff; padding: 6px 10px; font-size: 10px; text-align: right; }
  .road-table td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
  .road-table tr:nth-child(even) td { background: #f8fafc; }

  /* ── Footer ── */
  .footer {
    background: #12244E;
    color: #fff;
    text-align: center;
    padding: 14px;
    font-size: 9px;
    opacity: .9;
  }

  .page-break { page-break-after: always; }
</style>
</head>
<body>

<!-- ════════════════════════════════════════════════
     COVER
═══════════════════════════════════════════════════ -->
<div class="cover">
  <h1>تقرير تقييم نظام نقطة البيع</h1>
  <h2>POS Laravel — تحليل شامل بعد الإصلاحات</h2>
  <div class="score-box">91 / 100</div>
  <div style="font-size:12px; opacity:.8; margin-top:8px;">ارتفع من 87 → 91 بعد 13 إصلاحاً</div>
  <div class="meta">
    تاريخ التقرير: {{ now()->format('d / m / Y') }} &nbsp;|&nbsp;
    الاختبارات: 336 ✓ &nbsp;|&nbsp;
    مدة التقييم: جلستان
  </div>
</div>


<!-- ════════════════════════════════════════════════
     PAGE 1 — SCORE SUMMARY
═══════════════════════════════════════════════════ -->
<div class="section">
  <div class="section-title">ملخص الدرجات — مقارنة قبل وبعد الإصلاحات</div>

  <table class="score-table">
    <thead>
      <tr>
        <th>المجال</th>
        <th style="text-align:center">الوزن</th>
        <th style="text-align:center">قبل</th>
        <th style="text-align:center">بعد</th>
        <th style="text-align:center">التغيير</th>
        <th>مستوى</th>
        <th>شريط التقدم</th>
      </tr>
    </thead>
    <tbody>
      @php
        $domains = [
          ['هيكلة الكود وClean Architecture', '20%', 92, 95, '+3'],
          ['الأمان والحماية', '25%', 87, 92, '+5'],
          ['قاعدة البيانات والأداء', '20%', 78, 87, '+9'],
          ['الاختبارات والتغطية', '10%', 76, 82, '+6'],
          ['ميزات الأعمال', '20%', 95, 95, '—'],
          ['التوثيق والـ DevOps', '5%', 85, 90, '+5'],
        ];
        $colors = ['#00B04E','#00B04E','#3b82f6','#f59e0b','#00B04E','#3b82f6'];
        $labels = ['ممتاز','ممتاز','جيد جداً','جيد','ممتاز','جيد جداً'];
        $labelClass = ['badge-green','badge-green','badge-blue','badge-orange','badge-green','badge-blue'];
      @endphp

      @foreach($domains as $i => $d)
      <tr>
        <td style="font-weight:bold">{{ $d[0] }}</td>
        <td style="text-align:center">{{ $d[1] }}</td>
        <td style="text-align:center; color:#94a3b8">{{ $d[2] }}</td>
        <td style="text-align:center; font-weight:bold; color:#12244E">{{ $d[3] }}</td>
        <td style="text-align:center; color:#00B04E; font-weight:bold">{{ $d[4] }}</td>
        <td><span class="badge {{ $labelClass[$i] }}">{{ $labels[$i] }}</span></td>
        <td>
          <span class="bar-wrap">
            <span class="bar-fill" style="width:{{ $d[3] }}px; background:{{ $colors[$i] }}"></span>
          </span>
        </td>
      </tr>
      @endforeach

      <tr style="background:#f0fdf4; font-weight:bold; font-size:12px;">
        <td>الإجمالي المرجَّح</td>
        <td style="text-align:center">100%</td>
        <td style="text-align:center; color:#94a3b8">87</td>
        <td style="text-align:center; color:#00B04E; font-size:14px;">91</td>
        <td style="text-align:center; color:#00B04E">+4</td>
        <td><span class="badge badge-green">ممتاز</span></td>
        <td>
          <span class="bar-wrap">
            <span class="bar-fill" style="width:91px; background:#00B04E"></span>
          </span>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<div class="page-break"></div>


<!-- ════════════════════════════════════════════════
     PAGE 2 — DOMAIN DETAILS
═══════════════════════════════════════════════════ -->
<div class="section">
  <div class="section-title">تفصيل كل مجال</div>

  <div class="two-col">
    <div class="col">

      <!-- هيكلة الكود -->
      <div class="domain-card">
        <div class="domain-header">
          <span class="dh-title">هيكلة الكود</span>
          <span class="dh-change" style="color:#00B04E">↑ +3</span>
          <span class="dh-score">95</span>
        </div>
        <div class="domain-body">
          <ul class="checklist">
            <li>Thin Controllers + Services + Repositories + Interfaces</li>
            <li>16 Repository Interface مع تطبيق كامل</li>
            <li>22 Form Request — validation مستقل لكل عملية</li>
            <li>9 Policies للـ Authorization</li>
            <li>Traits: ApiResponse + AuditLog</li>
            <li>Queue Jobs للعمليات الثقيلة</li>
            <li>حذف _all_*.php dev artifacts</li>
            <li>إصلاح DashboardService stub المكسور</li>
          </ul>
        </div>
      </div>

      <!-- قاعدة البيانات -->
      <div class="domain-card">
        <div class="domain-header">
          <span class="dh-title">قاعدة البيانات والأداء</span>
          <span class="dh-change" style="color:#00B04E">↑ +9</span>
          <span class="dh-score">87</span>
        </div>
        <div class="domain-body">
          <ul class="checklist">
            <li>79 Migration + 47 Index محدد</li>
            <li>FIFO / LIFO / WAC — تكلفة فعلية</li>
            <li>Double-entry Accounting مع توازن debit/credit</li>
            <li>Fiscal Period locking</li>
            <li>Dashboard cache 60 ثانية + invalidation تلقائي</li>
            <li>totalRevenue cache 10 دقائق</li>
            <li>recentMovements: select أعمدة محددة</li>
            <li class="warn">لا Redis — cache يذهب للـ DB</li>
          </ul>
        </div>
      </div>

      <!-- ميزات الأعمال -->
      <div class="domain-card">
        <div class="domain-header">
          <span class="dh-title">ميزات الأعمال</span>
          <span class="dh-change" style="color:#94a3b8">—</span>
          <span class="dh-score">95</span>
        </div>
        <div class="domain-body">
          <ul class="checklist">
            <li>POS + Split Payment + Cash Register Sessions</li>
            <li>Offline mode مع Idempotency (UUID)</li>
            <li>Inventory: Batch + Expiry + Warehouses + Transfers</li>
            <li>ETA (الفاتورة الإلكترونية المصرية)</li>
            <li>WhatsApp + 4 Payment Gateways (HMAC verified)</li>
            <li>Loyalty Points + Customer Credit Limits</li>
            <li>Fraud Detection + Anomaly Detection</li>
            <li>Multi-tenancy: DB منفصل لكل مستأجر</li>
          </ul>
        </div>
      </div>

    </div>
    <div class="col">

      <!-- الأمان -->
      <div class="domain-card">
        <div class="domain-header">
          <span class="dh-title">الأمان والحماية</span>
          <span class="dh-change" style="color:#00B04E">↑ +5</span>
          <span class="dh-score">92</span>
        </div>
        <div class="domain-body">
          <ul class="checklist">
            <li>CSP مع nonce لكل request (بلا unsafe-inline scripts)</li>
            <li>lockForUpdate — منع Race Conditions في المخزون</li>
            <li>Rate Limiting: login (10/min) + APIs</li>
            <li>Session Fingerprinting لكشف الاختطاف</li>
            <li>IP Whitelist مع CIDR + Wildcard</li>
            <li>2FA middleware جاهز</li>
            <li>AuditLog ثنائي (ملف + DB) + immutable</li>
            <li>AnomalyDetection: حجب تلقائي 5 دقائق بعد 5 ضربات</li>
            <li>Impersonate guard: لا انتحال صفة Admin</li>
            <li>Tap webhook يتحقق من API قبل التفعيل</li>
            <li class="warn">CSS style-src-attr unsafe-inline (331 متبقي)</li>
            <li class="warn">BACKUP_ARCHIVE_PASSWORD فارغ في .env</li>
          </ul>
        </div>
      </div>

      <!-- الاختبارات -->
      <div class="domain-card">
        <div class="domain-header">
          <span class="dh-title">الاختبارات</span>
          <span class="dh-change" style="color:#00B04E">↑ +6</span>
          <span class="dh-score">82</span>
        </div>
        <div class="domain-body">
          <ul class="checklist">
            <li>336 Test — 24,620 Assertion — 0 Failures</li>
            <li>InvoiceService: إنشاء، خصم، مخزون، أرقام</li>
            <li>StockService: إضافة، خصم، حركات</li>
            <li>AccountingService: 10 اختبارات جديدة</li>
            <li>SequenceService: تسلسل، استقلالية، format</li>
            <li>Feature: Auth، Invoice، Unit، Customer، Security</li>
            <li class="warn">لا اختبارات على AnomalyDetection blocking</li>
            <li class="warn">لا اختبارات على ETA / Payment webhooks</li>
            <li class="warn">لا اختبارات على Offline sync edge cases</li>
          </ul>
        </div>
      </div>

      <!-- التوثيق -->
      <div class="domain-card">
        <div class="domain-header">
          <span class="dh-title">التوثيق والـ DevOps</span>
          <span class="dh-change" style="color:#00B04E">↑ +5</span>
          <span class="dh-score">90</span>
        </div>
        <div class="domain-body">
          <ul class="checklist">
            <li>FIXES.md: 13 إصلاح موثق بالتفصيل</li>
            <li>DEPLOYMENT.md مع Security checklist</li>
            <li>Docker + docker-compose جاهزان</li>
            <li>Artisan command: csp:audit-inline-styles</li>
            <li>خطة CSS migration مرحلية موثقة</li>
            <li>5 Console Commands للـ automation</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>

<div class="page-break"></div>


<!-- ════════════════════════════════════════════════
     PAGE 3 — FIXES LOG + ROADMAP
═══════════════════════════════════════════════════ -->
<div class="section">
  <div class="section-title">سجل الإصلاحات الكاملة (الجلستان)</div>

  @php
  $fixes = [
    ['FIX-1',  'SESSION_ENCRYPT=false', 'تشفير الجلسات إجباري في الإنتاج', 'متوسطة'],
    ['FIX-2',  'خصم بلا حد أقصى', 'MAX_DISCOUNT_PERCENT + logging لمحاولات التجاوز', 'عالية'],
    ['FIX-3',  'ReturnController بلا authorize()', 'إضافة Policy check + تسجيل في AppServiceProvider', 'متوسطة'],
    ['FIX-4',  'auditTrail بلا تحقق من وجود المنتج', 'findOrFail() بدلاً من find() لإرجاع 404 نظيف', 'منخفضة'],
    ['FIX-5',  'SequenceService يفشل صامتاً', 'Log::warning() عند الـ fallback + إعادة قراءة القيمة الفعلية', 'منخفضة'],
    ['FIX-6',  'Session timeout غير متزامن', 'config("session.lifetime") بدلاً من القيمة الثابتة', 'منخفضة'],
    ['FIX-7',  'CSP يسمح بـ unsafe-inline للـ scripts', 'إزالة unsafe-inline من script-src + nonce بديلاً', 'متوسطة'],
    ['FIX-8',  'AnomalyDetection لا تراقب الخصم', 'detectDiscountCapViolation() بعد 3 محاولات', 'منخفضة'],
    ['FIX-9',  'clear-cache.php بـ secret في الكود', 'حذف الملف نهائياً — استخدم artisan مباشرة', 'عالية'],
    ['FIX-10', 'Tap webhook يُفعّل بلا توثيق', 'التحقق من Tap API بالـ charge_id قبل التفعيل', 'حرجة'],
    ['FIX-11', 'WHATSAPP_VERIFY_TOKEN قيمة افتراضية ضعيفة', 'القيمة الآن فارغة في .env.example مع تحذير', 'متوسطة'],
    ['FIX-12', 'نقاط ضعف متعددة (جلسة التقييم)', 'Dashboard cache + AnomalyDetection blocking + Impersonate guard + 10 Unit tests', 'متعددة'],
    ['FIX-13', 'CSS style-src-attr unsafe-inline', 'مرحلة 1: تنظيف 13 ملف + utility classes + audit command', 'متوسطة'],
  ];
  $badgeMap = ['عالية' => 'badge-red', 'حرجة' => 'badge-red', 'متوسطة' => 'badge-orange', 'منخفضة' => 'badge-green', 'متعددة' => 'badge-blue'];
  @endphp

  @foreach($fixes as $fix)
  <div class="fix-row">
    <span class="fix-num">{{ $fix[0] }}</span>
    <span class="fix-body">
      <span class="fix-title">{{ $fix[1] }}</span>
      <span class="badge {{ $badgeMap[$fix[3]] }}" style="margin-right:6px">{{ $fix[3] }}</span><br>
      <span class="fix-desc">{{ $fix[2] }}</span>
    </span>
  </div>
  @endforeach
</div>


<div class="section">
  <div class="section-title">خارطة الطريق — للوصول إلى 95+</div>

  <table class="road-table">
    <thead>
      <tr>
        <th>المهمة</th>
        <th style="text-align:center">الأولوية</th>
        <th style="text-align:center">التأثير على الدرجة</th>
        <th>الأداة / الطريقة</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>تنظيف 331 inline style متبقية (مرحلة 2+3)</td>
        <td style="text-align:center"><span class="badge badge-red">عالية</span></td>
        <td style="text-align:center">أمان: 92→96</td>
        <td><code>php artisan csp:audit-inline-styles</code></td>
      </tr>
      <tr>
        <td>تشفير نسخ الـ Backup (BACKUP_ARCHIVE_PASSWORD)</td>
        <td style="text-align:center"><span class="badge badge-red">عالية</span></td>
        <td style="text-align:center">DevOps: 90→93</td>
        <td>تعيين قيمة في .env + php artisan backup:run</td>
      </tr>
      <tr>
        <td>اختبارات AnomalyDetection blocking behavior</td>
        <td style="text-align:center"><span class="badge badge-orange">متوسطة</span></td>
        <td style="text-align:center">اختبارات: 82→86</td>
        <td>Feature test: 5 anomaly strikes → 429</td>
      </tr>
      <tr>
        <td>اختبارات Payment Webhooks (HMAC mock)</td>
        <td style="text-align:center"><span class="badge badge-orange">متوسطة</span></td>
        <td style="text-align:center">اختبارات: 86→89</td>
        <td>Http::fake() في PaymobController/StripeController</td>
      </tr>
      <tr>
        <td>Redis للـ Cache والـ Queue</td>
        <td style="text-align:center"><span class="badge badge-orange">متوسطة</span></td>
        <td style="text-align:center">أداء: 87→92</td>
        <td>CACHE_STORE=redis + QUEUE_CONNECTION=redis</td>
      </tr>
      <tr>
        <td>2FA إلزامي للـ Admin</td>
        <td style="text-align:center"><span class="badge badge-orange">متوسطة</span></td>
        <td style="text-align:center">أمان: +1</td>
        <td>EnforceTwoFactor middleware على admin routes</td>
      </tr>
    </tbody>
  </table>
</div>


<!-- Footer -->
<div class="footer">
  نظام نقطة البيع — POS Laravel 11 &nbsp;|&nbsp;
  التقييم الإجمالي: 91/100 &nbsp;|&nbsp;
  تاريخ الإصدار: {{ now()->format('d-m-Y') }} &nbsp;|&nbsp;
  الاختبارات: 336 passed · 0 failed
</div>

</body>
</html>
