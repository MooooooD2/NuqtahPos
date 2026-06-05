<?php

// #25 API routes منفصلة عن web
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\BackupMonitorController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CashbackController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceSessionController;
use App\Http\Controllers\DynamicPricingController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FiscalPeriodController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\FraudDetectionController;
use App\Http\Controllers\HeldInvoiceController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KitchenDisplayController;
use App\Http\Controllers\OfflineSyncController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfitReportController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\QrOrderController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockReconciliationController;
use App\Http\Controllers\SupplierAccountController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierPaymentController;
use App\Http\Controllers\TaxCategoryController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitConversionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WasteController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Middleware\CheckSubscriptionActive;
use Illuminate\Support\Facades\Route;

// ── SPA Auth (no auth middleware — these are public login endpoints) ─────────
Route::post('/login', [App\Http\Controllers\Auth\AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [App\Http\Controllers\Auth\AuthController::class, 'logout'])->middleware('auth:sanctum');

// #12 Rate Limiting: 60 طلب/دقيقة على كل APIs
Route::middleware(['auth:sanctum', 'throttle:60,1', CheckSubscriptionActive::class])->group(function () {

    Route::get('/dashboard-data', [DashboardController::class, 'data'])->name('api.dashboard.data');

    // Units — read is available to all authenticated users (POS needs units for product display)
    Route::get('/units', [UnitController::class, 'all'])->name('units.all');

    // Tax categories — read available to all (POS needs rates for display)
    Route::get('/tax-categories', [TaxCategoryController::class, 'all'])->name('tax-categories.all');

    // Customers (search available to POS users; CRUD to warehouse+)
    Route::middleware('permission:view_pos')->group(function () {
        Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    });
    Route::middleware('permission:view_warehouse')->group(function () {
        Route::get('/customers', [CustomerController::class, 'all'])->name('customers.all');
        Route::post('/customers', [CustomerController::class, 'store'])->middleware('throttle:30,1')->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        // Customer groups
        Route::get('/customer-groups', [CustomerGroupController::class, 'index'])->name('customer-groups.index');
        Route::post('/customer-groups', [CustomerGroupController::class, 'store'])->middleware('throttle:30,1')->name('customer-groups.store');
        Route::get('/customer-groups/{customerGroup}', [CustomerGroupController::class, 'show'])->name('customer-groups.show');
        Route::put('/customer-groups/{customerGroup}', [CustomerGroupController::class, 'update'])->name('customer-groups.update');
        Route::delete('/customer-groups/{customerGroup}', [CustomerGroupController::class, 'destroy'])->name('customer-groups.destroy');
    });

    // POS
    Route::middleware('permission:view_pos')->group(function () {
        Route::get('/search-product', [InvoiceController::class, 'searchProduct'])->name('products.search');
        Route::get('/products/for-cache', [InvoiceController::class, 'productsForCache'])->name('products.for-cache');

        // Barcode name lookup proxy — server-side so CSP connect-src 'self' is respected
        Route::get('/barcode-lookup/{barcode}', function (string $barcode) {
            // Validate: only numeric EAN/UPC barcodes (6–14 digits)
            if (! preg_match('/^\d{6,14}$/', $barcode)) {
                return response()->json(['name' => null], 400);
            }

            $locale = app()->getLocale();

            // 1. Try Open Food Facts (free, no key, supports Arabic)
            try {
                $off = Illuminate\Support\Facades\Http::timeout(6)
                    ->withoutVerifying()
                    ->get("https://world.openfoodfacts.org/api/v2/product/{$barcode}.json", [
                        'fields' => 'product_name,product_name_ar,brands,categories_tags',
                    ]);

                if ($off->successful()) {
                    $data = $off->json();
                    if (($data['status'] ?? 0) === 1 && isset($data['product'])) {
                        $p = $data['product'];
                        $name = $locale === 'ar'
                            ? ($p['product_name_ar'] ?? $p['product_name'] ?? null)
                            : ($p['product_name'] ?? null);
                        $name = trim($name ?? '');

                        if ($name !== '') {
                            return response()->json([
                                'name' => $name,
                                'brand' => isset($p['brands']) ? trim(explode(',', $p['brands'])[0]) : null,
                                'source' => 'openfoodfacts',
                            ]);
                        }
                    }
                }
            } catch (Throwable) {
                // silent fallback
            }

            // 2. Fallback: UPC Item DB (free trial, no key, general products)
            try {
                $upc = Illuminate\Support\Facades\Http::timeout(5)
                    ->withoutVerifying()
                    ->get('https://api.upcitemdb.com/prod/trial/lookup', ['upc' => $barcode]);

                if ($upc->successful()) {
                    $data = $upc->json();
                    $items = $data['items'] ?? [];
                    if (! empty($items)) {
                        $item = $items[0];
                        $name = trim($item['title'] ?? '');
                        if ($name !== '') {
                            return response()->json([
                                'name' => $name,
                                'brand' => isset($item['brand']) ? trim($item['brand']) : null,
                                'source' => 'upcitemdb',
                            ]);
                        }
                    }
                }
            } catch (Throwable) {
                // silent fallback
            }

            return response()->json(['name' => null]);
        })->name('barcode.lookup')->middleware('throttle:60,1');
        Route::post('/offline/sync', [OfflineSyncController::class, 'sync'])->name('offline.sync');
        Route::post('/invoices', [InvoiceController::class, 'createInvoice'])->name('invoices.create');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/search', [InvoiceController::class, 'getByNumber'])->name('invoices.by-number');
        Route::get('/invoices/{invoice}/returnable-items', [InvoiceController::class, 'returnableItems'])->name('invoices.returnable-items');
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->middleware('throttle:20,1')->name('invoices.cancel');
        Route::get('/invoices/eta-log', [InvoiceController::class, 'etaSubmissionLog'])->name('invoices.eta-log');

        // Held Invoices
        Route::get('/held-invoices', [HeldInvoiceController::class, 'active'])->name('held-invoices.active');
        Route::post('/held-invoices', [HeldInvoiceController::class, 'store'])->name('held-invoices.store');
        Route::post('/held-invoices/{heldInvoice}/resume', [HeldInvoiceController::class, 'resume'])->name('held-invoices.resume');
        Route::delete('/held-invoices/{heldInvoice}', [HeldInvoiceController::class, 'discard'])->name('held-invoices.discard');

        // Expenses
        Route::get('/expense-categories', [ExpenseController::class, 'categories'])->name('expenses.categories');
        Route::get('/expenses', [ExpenseController::class, 'all'])->name('expenses.all');
        Route::post('/expenses/summary', [ExpenseController::class, 'summary'])->name('expenses.summary');
        Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('throttle:30,1')->name('expenses.store');
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    // Returns
    Route::middleware('permission:view_returns')->group(function () {
        Route::get('/returns', [ReturnController::class, 'all'])->name('returns.all');
        Route::post('/returns', [ReturnController::class, 'store'])->name('returns.store');
    });

    // Warehouse
    Route::middleware('permission:view_warehouse')->group(function () {
        Route::get('/products', [ProductController::class, 'all'])->name('products.all');
        Route::get('/products/by-barcode', [ProductController::class, 'lookupByBarcode'])->name('products.by-barcode');
        Route::post('/products', [ProductController::class, 'store'])->middleware('throttle:30,1')->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/add-stock', [ProductController::class, 'addStock'])->middleware('throttle:30,1')->name('products.add-stock');
        Route::get('/products/{product}/recipe', [RecipeController::class, 'show'])->name('products.recipe.show');
        Route::post('/products/{product}/recipe', [RecipeController::class, 'sync'])->name('products.recipe.sync');
        Route::get('/products/{product}/unit-conversion', [UnitConversionController::class, 'show'])->name('products.unit-conversion.show');
        Route::post('/products/{product}/unit-conversion', [UnitConversionController::class, 'upsert'])->name('products.unit-conversion.upsert');
        Route::delete('/products/{product}/unit-conversion', [UnitConversionController::class, 'destroy'])->name('products.unit-conversion.destroy');

        // Units — write operations (manage_roles is warehouse-level access)
        Route::post('/units', [UnitController::class, 'store'])->name('units.store');
        Route::put('/units/{unit}', [UnitController::class, 'update'])->name('units.update');
        Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');

        // Tax categories — write (warehouse-level)
        Route::post('/tax-categories', [TaxCategoryController::class, 'store'])->name('tax-categories.store');
        Route::put('/tax-categories/{taxCategory}', [TaxCategoryController::class, 'update'])->name('tax-categories.update');
        Route::delete('/tax-categories/{taxCategory}', [TaxCategoryController::class, 'destroy'])->name('tax-categories.destroy');

        Route::get('/suppliers', [SupplierController::class, 'all'])->name('suppliers.all');
        Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('throttle:20,1')->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'all'])->name('purchase-orders.all');
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->middleware('throttle:20,1')->name('purchase-orders.store');
        Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->middleware('permission:approve_purchase_order')->name('purchase-orders.approve');
        Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])->middleware('permission:approve_purchase_order')->name('purchase-orders.reject');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');

        Route::get('/purchase-returns', [PurchaseReturnController::class, 'all'])->name('purchase-returns.all');
        Route::post('/purchase-returns', [PurchaseReturnController::class, 'store'])->middleware('throttle:20,1')->name('purchase-returns.store');
        Route::get('/purchase-orders/{purchaseOrder}/returnable-items', [PurchaseReturnController::class, 'returnableItems'])->name('purchase-orders.returnable-items');

        Route::get('/supplier-payments', [SupplierPaymentController::class, 'all'])->name('supplier-payments.all');
        Route::post('/supplier-payments', [SupplierPaymentController::class, 'store'])->middleware('throttle:20,1')->name('supplier-payments.store');

        Route::get('/supplier-accounts/{supplier}', [SupplierAccountController::class, 'show'])->name('supplier-accounts.show');

        // Multi-Warehouse
        Route::get('/warehouses/products-list', [WarehouseController::class, 'allProducts'])->name('warehouses.products-list');
        Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.all');
        Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
        Route::get('/warehouses/{warehouse}/stock', [WarehouseController::class, 'stock'])->name('warehouses.stock');
        Route::post('/warehouses/{warehouse}/adjust-stock', [WarehouseController::class, 'adjustStock'])->name('warehouses.adjust-stock');
        Route::post('/warehouses/{warehouse}/sync-stock', [WarehouseController::class, 'syncStock'])->name('warehouses.sync-stock');
        Route::post('/warehouses/{warehouse}/toggle-lock', [WarehouseController::class, 'toggleLock'])->name('warehouses.toggle-lock');

        // Warehouse Transfers
        Route::get('/warehouse-transfers', [WarehouseController::class, 'transfers'])->name('warehouse-transfers.all');
        Route::post('/warehouse-transfers', [WarehouseController::class, 'createTransfer'])->name('warehouse-transfers.store');
        Route::post('/warehouse-transfers/{transfer}/receive', [WarehouseController::class, 'receiveTransfer'])->name('warehouse-transfers.receive');
        Route::post('/warehouse-transfers/{transfer}/cancel', [WarehouseController::class, 'cancelTransfer'])->name('warehouse-transfers.cancel');

        // Product Batches (legacy WarehouseController routes kept for backward compatibility)
        Route::get('/product-batches', [WarehouseController::class, 'batches'])->name('batches.all');
        Route::post('/product-batches', [WarehouseController::class, 'createBatch'])->name('batches.store');

        // ── Stock health & smart alerts (controller checks view_reports internally) ──
        Route::get('/stock/health', [StockController::class, 'health'])->name('stock.health');
        Route::get('/stock/low-stock', [StockController::class, 'lowStock'])->name('stock.low-stock');
        Route::get('/stock/out-of-stock', [StockController::class, 'outOfStock'])->name('stock.out-of-stock');
        Route::get('/stock/near-expiry', [StockController::class, 'nearExpiry'])->name('stock.near-expiry');
        Route::get('/stock/expired-batches', [StockController::class, 'expiredBatches'])->name('stock.expired-batches');
        Route::get('/stock/reorder-suggestions', [StockController::class, 'reorderSuggestions'])->name('stock.reorder-suggestions');
        Route::get('/stock/all-products', [StockController::class, 'allProducts'])->name('stock.all-products');

        // ── Stock availability & reservations (controller checks add_stock internally) ──
        Route::get('/stock/available/{product}', [StockController::class, 'available'])->name('stock.available');
        Route::post('/stock/reserve', [StockController::class, 'reserve'])->middleware('throttle:30,1')->name('stock.reserve');
        Route::post('/stock/release-reservation', [StockController::class, 'releaseReservation'])->middleware('throttle:30,1')->name('stock.release-reservation');

        // ── Batch management via BatchService (controller checks add_stock / manage_roles) ──
        Route::get('/batches', [StockController::class, 'batches'])->name('stock.batches.index');
        Route::post('/batches', [StockController::class, 'createBatch'])->middleware('throttle:30,1')->name('stock.batches.store');
        Route::put('/batches/{batch}/adjust', [StockController::class, 'adjustBatch'])->name('stock.batches.adjust');
        Route::post('/batches/{batch}/write-off', [StockController::class, 'writeOffBatch'])->name('stock.batches.write-off');

        // ── Bulk expired write-off (controller checks manage_roles internally) ──
        Route::post('/stock/write-off-expired', [StockController::class, 'writeOffExpired'])->middleware('throttle:10,1')->name('stock.write-off-expired');
    });

    // Fiscal Periods
    Route::middleware('permission:view_accounting')->group(function () {
        Route::get('/fiscal-periods', [FiscalPeriodController::class, 'index'])->name('fiscal-periods.all');
        Route::get('/fiscal-periods/current', [FiscalPeriodController::class, 'current'])->name('fiscal-periods.current');
        Route::get('/fiscal-periods/{fiscalPeriod}/preview-close', [FiscalPeriodController::class, 'previewClose'])->name('fiscal-periods.preview-close');
    });
    Route::middleware('permission:manage_roles')->group(function () {
        Route::post('/fiscal-periods', [FiscalPeriodController::class, 'store'])->name('fiscal-periods.store');
        Route::post('/fiscal-periods/{fiscalPeriod}/close', [FiscalPeriodController::class, 'close'])->name('fiscal-periods.close');
        Route::post('/accounts/recalculate-balances', [AccountingController::class, 'recalculateBalances'])->name('accounts.recalculate-balances');
    });

    // Accounting
    Route::middleware('permission:view_accounting')->group(function () {
        Route::get('/accounts', [AccountingController::class, 'allAccounts'])->name('accounts.all');
        Route::post('/accounts', [AccountingController::class, 'storeAccount'])->name('accounts.store');
        Route::put('/accounts/{account}', [AccountingController::class, 'updateAccount'])->name('accounts.update');
        Route::delete('/accounts/{account}', [AccountingController::class, 'destroyAccount'])->name('accounts.destroy');
        Route::get('/journal-entries', [AccountingController::class, 'allJournalEntries'])->name('journal-entries.all');
        Route::post('/journal-entries', [AccountingController::class, 'storeJournalEntry'])->middleware('throttle:30,1')->name('journal-entries.store');
        Route::post('/journal-entries/{entry}/post', [AccountingController::class, 'postJournalEntry'])->name('journal-entries.post');
        Route::post('/journal-entries/{entry}/reverse', [AccountingController::class, 'reverseJournalEntry'])->name('journal-entries.reverse');
        Route::get('/audit-logs', [AccountingController::class, 'auditLogs'])->name('audit-logs.index');
    });

    // Settings — read is available to any authenticated user; update requires manage_roles
    Route::get('/settings', [SettingController::class, 'all'])->name('settings.all');
    Route::get('/settings/group/{group}', [SettingController::class, 'group'])->name('settings.group');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Reports
    Route::middleware('permission:view_reports')->group(function () {
        Route::post('/reports/sales', [ReportController::class, 'salesReport'])->middleware('throttle:60,1')->name('reports.sales');
        Route::get('/reports/stock', [ReportController::class, 'stockReport'])->name('reports.stock');
        Route::post('/reports/returns', [ReportController::class, 'returnsReport'])->middleware('throttle:60,1')->name('reports.returns');
        Route::post('/reports/income-statement', [ReportController::class, 'incomeStatement'])->middleware('throttle:60,1')->name('reports.income-statement');
        Route::post('/reports/cash-flow', [ReportController::class, 'cashFlowReport'])->middleware('throttle:60,1')->name('reports.cash-flow');
        Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::post('/reports/account-statement/{account}', [ReportController::class, 'accountStatement'])->name('reports.account-statement');
        Route::get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation'])->name('reports.inventory-valuation');
        Route::get('/reports/permissions-audit', [ReportController::class, 'permissionsAudit'])->name('reports.permissions-audit');
        Route::post('/reports/tax', [TaxCategoryController::class, 'report'])->name('reports.tax');
        Route::get('/reports/tax/monthly', [TaxCategoryController::class, 'monthlyReport'])->name('reports.tax.monthly');
        Route::post('/reports/revenue-monitoring', [ReportController::class, 'revenueMonitoring'])->name('reports.revenue-monitoring');
        Route::post('/reports/inventory-movements', [ReportController::class, 'inventoryMovements'])->name('reports.inventory-movements');
        Route::get('/reports/aged-receivables', [ReportController::class, 'agedReceivables'])->name('reports.aged-receivables');
        Route::get('/reports/aged-payables', [ReportController::class, 'agedPayables'])->name('reports.aged-payables');
        Route::post('/reports/best-selling', [ReportController::class, 'bestSellingProducts'])->name('reports.best-selling');
        Route::post('/reports/cashier-performance', [ReportController::class, 'cashierPerformance'])->name('reports.cashier-performance');
        Route::get('/reports/near-expiry', [ReportController::class, 'nearExpiryProducts'])->name('reports.near-expiry');
        Route::get('/reports/inventory-turnover', [ReportController::class, 'inventoryTurnover'])->name('reports.inventory-turnover');
        Route::get('/reports/waste-ratio', [ReportController::class, 'monthlyWasteRatio'])->name('reports.waste-ratio');
        Route::post('/reports/net-profit', [ReportController::class, 'netProfitReport'])->name('reports.net-profit');
        Route::get('/reports/net-profit/export', [ReportController::class, 'exportNetProfit'])->name('reports.net-profit.export');
        Route::post('/reports/profitable-products', [ReportController::class, 'profitableProducts'])->name('reports.profitable-products');
        Route::get('/reports/profitable-products/export', [ReportController::class, 'exportProfitableProducts'])->name('reports.profitable-products.export');
        Route::post('/reports/weekly-expenses', [ReportController::class, 'weeklyExpenses'])->name('reports.weekly-expenses');
        Route::post('/reports/break-even', [ReportController::class, 'breakEvenReport'])->name('reports.break-even');
        Route::get('/reports/kpi-dashboard', [ReportController::class, 'kpiDashboard'])->name('reports.kpi-dashboard');
        Route::post('/reports/supplier-rating', [ReportController::class, 'supplierRating'])->name('reports.supplier-rating');

        // Budget vs actual (item 41)
        Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
        Route::post('/budgets', [BudgetController::class, 'upsert'])->name('budgets.upsert');
        Route::delete('/budgets/{budget}', [BudgetController::class, 'destroy'])->name('budgets.destroy');
        Route::get('/reports/budget-vs-actual', [BudgetController::class, 'report'])->name('reports.budget-vs-actual');

        // Promotions (item 17)
        Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::get('/promotions/active', [PromotionController::class, 'active'])->name('promotions.active');
        Route::post('/promotions', [PromotionController::class, 'store'])->middleware('throttle:30,1')->name('promotions.store');
        Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
        Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
        Route::post('/promotions/preview', [PromotionController::class, 'preview'])->name('promotions.preview');
    });

    // Backup monitoring (admin)
    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/backup/status', [BackupMonitorController::class, 'status'])->name('backup.status');
    });

    // Fraud detection signals (admin)
    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/fraud/signals', [FraudDetectionController::class, 'signals'])->name('fraud.signals');
    });

    // Multi-Branch (admin)
    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/branches', [BranchController::class, 'index'])->name('branches.all');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });

    // ── Thermal Printing ───────────────────────────────────────────────────────
    Route::prefix('printing')->name('printing.')->group(function () {

        // Print a document (POS users can trigger prints)
        Route::middleware('permission:view_pos')->group(function () {
            Route::post('/print', [PrintController::class, 'printReceipt'])->middleware('throttle:30,1')->name('print');
            Route::post('/invoices/{invoice}/reprint', [PrintController::class, 'reprintInvoice'])->middleware('throttle:20,1')->name('invoices.reprint');
        });

        // Printer management (admin)
        Route::middleware('permission:manage_roles')->group(function () {
            Route::get('/printers', [PrintController::class, 'indexPrinters'])->name('printers.index');
            Route::post('/printers', [PrintController::class, 'storePrinter'])->middleware('throttle:20,1')->name('printers.store');
            Route::get('/printers/{printer}', [PrintController::class, 'showPrinter'])->name('printers.show');
            Route::put('/printers/{printer}', [PrintController::class, 'updatePrinter'])->name('printers.update');
            Route::delete('/printers/{printer}', [PrintController::class, 'destroyPrinter'])->name('printers.destroy');
            Route::post('/printers/{printer}/test', [PrintController::class, 'testPrinter'])->middleware('throttle:10,1')->name('printers.test');
            Route::post('/printers/{printer}/set-default', [PrintController::class, 'setDefaultPrinter'])->name('printers.set-default');
        });

        // Print job queue (admin)
        Route::middleware('permission:manage_roles')->group(function () {
            Route::get('/jobs', [PrintController::class, 'indexJobs'])->name('jobs.index');
            Route::post('/jobs/{job}/retry', [PrintController::class, 'retryJob'])->name('jobs.retry');
            Route::delete('/jobs/{job}', [PrintController::class, 'cancelJob'])->name('jobs.cancel');
            Route::get('/queue/stats', [PrintController::class, 'queueStats'])->name('queue.stats');
        });
    });

    // WhatsApp admin API
    Route::middleware('permission:manage_roles')->prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::get('/logs', [WhatsAppController::class, 'logs'])->name('logs');
        Route::get('/stats', [WhatsAppController::class, 'stats'])->name('stats');
        Route::post('/invoices/{invoice}/send', [WhatsAppController::class, 'sendInvoice'])->name('send-invoice');
        Route::post('/customers/{customer}/reminder', [WhatsAppController::class, 'sendDebtReminder'])->name('send-reminder');
        Route::post('/customers/bulk-reminders', [WhatsAppController::class, 'sendBulkDebtReminders'])->name('bulk-reminders');
        Route::post('/promotions', [WhatsAppController::class, 'sendPromotion'])->name('promotions');
    });

    // User & Role Management
    Route::middleware('permission:manage_roles')->group(function () {
        Route::get('/users', [UserController::class, 'all'])->name('users.all');
        Route::post('/users', [UserController::class, 'store'])->middleware('throttle:20,1')->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        Route::get('/roles', [RolePermissionController::class, 'getRoles'])->name('roles.all');
        Route::get('/permissions', [RolePermissionController::class, 'getPermissions'])->name('permissions.all');
        Route::post('/roles', [RolePermissionController::class, 'storeRole'])->name('roles.store');
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('roles.update');
        Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole'])->name('roles.destroy');
        Route::post('/roles/{role}/permissions', [RolePermissionController::class, 'syncPermissions'])->name('roles.sync-permissions');
        Route::get('/users/{user}/roles', [RolePermissionController::class, 'getUserRoles'])->name('users.roles');
        Route::post('/users/{user}/roles', [RolePermissionController::class, 'assignUserRole'])->name('users.assign-role');
    });
});

// WhatsApp Webhook (public — Meta verification + inbound messages)
Route::prefix('webhook/whatsapp')->name('webhook.whatsapp.')->group(function () {
    Route::get('/', [WhatsAppController::class, 'verifyWebhook'])->name('verify');
    Route::post('/', [WhatsAppController::class, 'receiveWebhook'])->name('receive');
});

// Stock Reconciliation #21
Route::middleware(['auth', 'permission:add_stock', 'throttle:30,1'])->group(function () {
    Route::post('/stock/reconcile', [StockReconciliationController::class, 'reconcile'])->name('stock.reconcile');
    Route::get('/stock/audit-trail/{productId}', [StockReconciliationController::class, 'auditTrail'])->name('stock.audit-trail');
});

// Waste / Spoilage Recording
Route::middleware(['auth', 'permission:add_stock', 'throttle:30,1'])->group(function () {
    Route::post('/waste', [WasteController::class, 'store'])->name('waste.store');
    Route::get('/waste', [WasteController::class, 'history'])->name('waste.history');
});

// ── تسوية الخزينة ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:view_pos', 'throttle:60,1'])->group(function () {
    Route::get('/cash-session/current', [CashRegisterController::class, 'currentSession'])->name('cash-session.current');
    Route::post('/cash-session/open', [CashRegisterController::class, 'open'])->name('cash-session.open');
    Route::post('/cash-session/{id}/close', [CashRegisterController::class, 'close'])->name('cash-session.close');
    Route::post('/cash-session/{id}/movements', [CashRegisterController::class, 'recordMovement'])->name('cash-session.movement');
    Route::get('/cash-session/history', [CashRegisterController::class, 'history'])->name('cash-session.history');

    // تقارير الربحية
    Route::middleware('permission:view_reports')->group(function () {
        Route::post('/reports/profit-by-product', [ProfitReportController::class, 'byProduct'])->name('reports.profit-product');
        Route::post('/reports/profit-daily', [ProfitReportController::class, 'daily'])->name('reports.profit-daily');
    });
});

// ── Kitchen Display System API ────────────────────────────────────────────
Route::middleware(['auth', 'permission:view_pos', 'throttle:120,1'])->prefix('kitchen')->group(function () {
    Route::get('/', [KitchenDisplayController::class, 'orders'])->name('api.kitchen.orders');
    Route::post('/', [KitchenDisplayController::class, 'store'])->name('api.kitchen.store');
    Route::post('/{id}/accept', [KitchenDisplayController::class, 'accept'])->name('api.kitchen.accept');
    Route::post('/{id}/ready', [KitchenDisplayController::class, 'ready'])->name('api.kitchen.ready');
    Route::post('/{id}/served', [KitchenDisplayController::class, 'served'])->name('api.kitchen.served');
    Route::post('/{id}/cancel', [KitchenDisplayController::class, 'cancel'])->name('api.kitchen.cancel');
    Route::patch('/items/{itemId}/status', [KitchenDisplayController::class, 'updateItem'])->name('api.kitchen.item.status');
    Route::get('/stats', [KitchenDisplayController::class, 'stats'])->name('api.kitchen.stats');
});

// ── QR Orders API ─────────────────────────────────────────────────────────
Route::middleware(['throttle:30,1'])->prefix('qr')->group(function () {
    Route::get('/{token}/products', [QrOrderController::class, 'products'])->name('api.qr.products');
    Route::post('/{token}/order', [QrOrderController::class, 'placeOrder'])->name('api.qr.order');
    Route::get('/order/{id}/status', [QrOrderController::class, 'orderStatus'])->name('api.qr.order.status');
});

// ── Forecasting API ───────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:view_reports', 'throttle:30,1'])->group(function () {
    Route::get('/forecast/sales', [ForecastController::class, 'salesForecast'])->name('api.forecast.sales');
    Route::get('/forecast/products', [ForecastController::class, 'productForecast'])->name('api.forecast.products');
    Route::get('/forecast/stock', [ForecastController::class, 'stockForecast'])->name('api.forecast.stock');
});

// ── Dynamic Pricing API ───────────────────────────────────────────────────
Route::middleware(['auth', 'permission:view_pos', 'throttle:60,1'])->prefix('pricing-rules')->group(function () {
    Route::get('/', [DynamicPricingController::class, 'all'])->name('api.pricing-rules.all');
    Route::post('/', [DynamicPricingController::class, 'store'])->name('api.pricing-rules.store');
    Route::put('/{id}', [DynamicPricingController::class, 'update'])->name('api.pricing-rules.update');
    Route::delete('/{id}', [DynamicPricingController::class, 'destroy'])->name('api.pricing-rules.destroy');
    Route::patch('/{id}/toggle', [DynamicPricingController::class, 'toggle'])->name('api.pricing-rules.toggle');
    Route::post('/evaluate', [DynamicPricingController::class, 'evaluate'])->name('api.pricing-rules.evaluate');
});

// ── Device Sessions API ───────────────────────────────────────────────────
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/device-sessions', [DeviceSessionController::class, 'list'])->name('api.device-sessions.list');
    Route::delete('/device-sessions/revoke-all', [DeviceSessionController::class, 'revokeAll'])->name('api.device-sessions.revoke-all');
    Route::delete('/device-sessions/{id}', [DeviceSessionController::class, 'revoke'])->name('api.device-sessions.revoke');
});

// ── Cashback API ──────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:view_pos', 'throttle:60,1'])->group(function () {
    Route::get('/cashback/customer/{id}', [CashbackController::class, 'balance'])->name('api.cashback.balance');
    Route::post('/cashback/redeem', [CashbackController::class, 'redeem'])->name('api.cashback.redeem');
    Route::get('/cashback/history', [CashbackController::class, 'history'])->name('api.cashback.history');
    // Cashback rules management
    Route::get('/cashback/rules', [CashbackController::class, 'rules'])->name('api.cashback.rules');
    Route::post('/cashback/rules', [CashbackController::class, 'storeRule'])->name('api.cashback.rules.store');
    Route::patch('/cashback/rules/{id}/activate', [CashbackController::class, 'activateRule'])->name('api.cashback.rules.activate');
    Route::delete('/cashback/rules/{id}', [CashbackController::class, 'deleteRule'])->name('api.cashback.rules.destroy');
});

// ── CRM API ───────────────────────────────────────────────────────────────
Route::middleware(['auth', 'permission:view_warehouse', 'throttle:60,1'])->prefix('crm')->group(function () {
    Route::get('/customers/{id}/activities', [CrmController::class, 'activities'])->name('api.crm.activities');
    Route::post('/activities', [CrmController::class, 'storeActivity'])->name('api.crm.store');
    Route::put('/activities/{id}', [CrmController::class, 'updateActivity'])->name('api.crm.update');
    Route::delete('/activities/{id}', [CrmController::class, 'deleteActivity'])->name('api.crm.delete');
    Route::get('/follow-ups', [CrmController::class, 'followUps'])->name('api.crm.followups');
    Route::get('/stats', [CrmController::class, 'stats'])->name('api.crm.stats');
});

// ── HR Module API ─────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::middleware('permission:manage_hr')->group(function () {
        Route::get('/hr/employees', [HrController::class, 'index'])->name('hr.employees.index');
        Route::post('/hr/employees', [HrController::class, 'store'])->name('hr.employees.store');
        Route::put('/hr/employees/{user}', [HrController::class, 'update'])->name('hr.employees.update');
        Route::delete('/hr/employees/{user}', [HrController::class, 'destroy'])->name('hr.employees.destroy');
    });
    Route::get('/shifts', [HrController::class, 'shifts'])->name('hr.shifts.index');
});

// ── CRM Follow-up store (missing from main CRM group) ────────────────────
Route::middleware(['auth:sanctum', 'permission:view_warehouse', 'throttle:60,1'])->group(function () {
    Route::post('/crm/follow-ups', [CrmController::class, 'storeFollowUp'])->name('api.crm.followup.store');
    Route::get('/crm/activities', [CrmController::class, 'allActivities'])->name('api.crm.activities.all');
});

// ── Stock adjustment (single product) ─────────────────────────────────────
Route::middleware(['auth:sanctum', 'permission:add_stock', 'throttle:30,1'])->group(function () {
    Route::post('/stock/adjustment', [StockController::class, 'adjustSingle'])->name('stock.adjust-single');
});

// ── Customers search endpoint ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
});

// ── Warehouse lock/unlock aliases ──────────────────────────────────────────
Route::middleware(['auth:sanctum', 'permission:view_warehouse', 'throttle:30,1'])->group(function () {
    Route::put('/warehouses/{warehouse}/lock', [WarehouseController::class, 'toggleLock'])->name('warehouses.lock');
    Route::put('/warehouses/{warehouse}/unlock', [WarehouseController::class, 'toggleLock'])->name('warehouses.unlock');
});

// ── Dev-only: fire a test notification to verify the pipeline ─────────────
Route::middleware(['auth:sanctum', 'permission:manage_roles'])->post('/dev/test-notification', function () {
    $type = request()->input('type', 'custom');
    $notifier = app(App\Services\NotificationService::class);

    match ($type) {
        'low_stock'   => $notifier->lowStock(1, 'Test Product', 3, 10),
        'new_invoice' => $notifier->newInvoice('INV-TEST-001', 99.99, 'cash', 'Test Customer'),
        'leave'       => $notifier->leaveRequest('Test Employee', 'annual', now()->toDateString(), now()->addDays(3)->toDateString(), 4),
        default       => $notifier->custom('Test notification', 'This is a test subtitle from the backend.'),
    };

    return response()->json(['success' => true, 'message' => "Sent [{$type}] notification."]);
})->name('dev.test-notification');

require __DIR__ . '/_api_additions.php';
