# دليل النشر — Production Deployment Guide

## ✅ قبل النشر

### 1. إعداد المتغيرات
```bash
cp .env.example .env
# عدّل القيم التالية:
APP_ENV=production
APP_DEBUG=false          # مهم جداً
APP_KEY=                 # سيُولَّد تلقائياً

DB_HOST=your_db_host
DB_DATABASE=pos_db
DB_USERNAME=pos_user
DB_PASSWORD=STRONG_PASS  # كلمة مرور قوية

# كلمات مرور المستخدمين الافتراضيين
ADMIN_PASSWORD=          # غيّرها!
CASHIER_PASSWORD=        # غيّرها!
```

### 2. تثبيت وإعداد
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

### 3. تحسين الأداء
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan icons:cache 2>/dev/null || true
```

### 4. إعداد Queue Worker
```bash
# supervisor أو systemd
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

### 5. جدول المهام (Cron)
```bash
# أضف في crontab -e
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### 6. صلاحيات الملفات
```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

---

## 🔒 قائمة التحقق الأمني

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] كلمات مرور قوية في `.env`
- [ ] HTTPS مُفعَّل (SSL/TLS)
- [ ] Firewall مُعدّ (منع الوصول المباشر للـ DB)
- [ ] `php artisan config:cache` مُشغَّل
- [ ] مجلد `storage/` خارج الـ web root
- [ ] ملف `.env` غير مرئي من المتصفح
- [ ] تحديثات PHP و Laravel مُطبَّقة
- [ ] نسخ احتياطية يومية للـ Database

---

## 📊 مراقبة الأداء

```bash
# فحص صحة التطبيق
curl https://your-domain.com/up

# مراجعة الـ Audit Log
tail -f storage/logs/audit.log

# مراجعة تنبيهات المخزون
php artisan stock:alert

# تشغيل الاختبارات
php artisan test
```

---

## 🔄 بعد كل تحديث

```bash
php artisan down                    # وضع الصيانة
git pull
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up                      # إلغاء الصيانة
```
