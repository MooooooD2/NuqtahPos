# توثيق الـ API — POS Laravel System

## المصادقة
جميع الـ API endpoints تتطلب تسجيل دخول عبر `POST /login`

## الحدود (Rate Limits)
| Endpoint | الحد |
|----------|------|
| POST /login | 5 طلبات/دقيقة |
| كل APIs | 60 طلب/دقيقة |
| POST /api/reports/* | 10 طلبات/دقيقة |

## شكل الاستجابة الموحد
```json
// نجاح
{ "success": true, "data": { ... } }

// خطأ
{ "success": false, "message": "وصف الخطأ", "errors": {} }
```

## Auth
### POST /login
```json
// Request
{ "username": "admin", "password": "Secret123" }
// Response 200: { "success": true, "redirect": "/dashboard" }
// Response 403: { "success": false, "message": "هذا الحساب معطّل." }
```

## POS (view_pos)
### GET /api/search-product?query=xxx&exact=false
### POST /api/invoices
```json
// Request — السعر يُحسب من DB لا من المستخدم
{
  "items": [{ "product_id": 1, "quantity": 2 }],
  "discount": 10.00,
  "payment_method": "cash|card|transfer|wallet"
}
// Response 201: { "success": true, "invoice": { "invoice_number": "INV-001", "final_total": 218.50, ... } }
// Response 422: { "success": false, "message": "المخزون غير كافٍ" }
```
### GET /api/invoices?number=INV-001
### GET /api/invoices/{id}/returnable-items

## المرتجعات (view_returns)
### POST /api/returns
```json
{ "invoice_id": 123, "items": [{ "product_id": 1, "quantity": 1 }], "reason": "تالف" }
```

## المخزون (view_warehouse)
### GET /api/products?search=&category=&low_stock=&per_page=50&all=false
### POST /api/products (add_product)
### PUT /api/products/{id} (edit_product)
### DELETE /api/products/{id} (delete_product)
### POST /api/products/{id}/add-stock (add_stock)
```json
{ "quantity": 20, "reason": "استلام", "reference_type": "purchase|adjustment|return|initial" }
```

## الموردون (view_warehouse)
### GET /api/suppliers | POST /api/suppliers | PUT /api/suppliers/{id} | DELETE /api/suppliers/{id}
### POST /api/purchase-orders (create_purchase_order)
### POST /api/purchase-orders/{id}/receive (receive_purchase_order)
### GET /api/supplier-payments | POST /api/supplier-payments
### GET /api/supplier-accounts/{supplier}

## المحاسبة (view_accounting)
### GET /api/accounts | POST /api/accounts | PUT /api/accounts/{id} | DELETE /api/accounts/{id}
### GET /api/journal-entries | POST /api/journal-entries
**ملاحظة: القيود المحاسبية يجب أن تكون متوازنة (مدين = دائن)**
### POST /api/reports/income-statement | GET /api/reports/balance-sheet
### GET /api/settings | POST /api/settings

## التقارير (view_reports)
### POST /api/reports/sales
```json
{ "start_date": "2026-04-01", "end_date": "2026-04-30", "payment_method": "cash" }
```
### GET /api/reports/stock
### POST /api/reports/returns

## المستخدمون والأدوار (manage_roles)
### GET /api/users — لا يُعيد password أبداً
### POST /api/users | PUT /api/users/{id} | DELETE /api/users/{id}
### POST /api/users/{id}/toggle-active
### GET /api/roles | POST /api/roles | PUT /api/roles/{id} | DELETE /api/roles/{id}
### POST /api/roles/{id}/permissions

## رموز الأخطاء
| الرمز | المعنى |
|-------|--------|
| 401 | غير مصرح |
| 403 | لا صلاحية |
| 404 | غير موجود |
| 422 | خطأ في البيانات |
| 429 | طلبات كثيرة |
