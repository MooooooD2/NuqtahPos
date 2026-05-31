{{-- FILE: resources/views/reports/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.reports'))
@section('page-title', __('pos.reports'))

@section('content')
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#salesTab">
                <i class="fas fa-chart-line me-1"></i>{{ __('pos.sales_report') }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#returnsTab" data-fn="loadReturnsReport">
                <i class="fas fa-undo-alt me-1"></i>{{ __('pos.Returns Report') }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stockTab" data-fn="loadStockReport">
                <i class="fas fa-boxes me-1"></i>{{ __('pos.stock_report') }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taxMonthlyTab" data-fn="loadTaxMonthlyReport">
                <i class="fas fa-percent me-1"></i>{{ app()->getLocale() === 'ar' ? 'الضريبة الشهرية' : 'Monthly Tax' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#revenueMonTab" data-fn="loadRevenueMonitoring">
                <i class="fas fa-chart-area me-1"></i>{{ app()->getLocale() === 'ar' ? 'مراقبة الإيرادات' : 'Revenue Monitoring' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#netProfitTab" data-fn="loadNetProfit">
                <i class="fas fa-sack-dollar me-1"></i>{{ app()->getLocale() === 'ar' ? 'صافي الربح' : 'Net Profit' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profitableProductsTab" data-fn="loadProfitableProducts">
                <i class="fas fa-trophy me-1"></i>{{ app()->getLocale() === 'ar' ? 'أكثر ربحية' : 'Most Profitable' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#weeklyExpensesTab" data-fn="loadWeeklyExpenses">
                <i class="fas fa-receipt me-1"></i>{{ app()->getLocale() === 'ar' ? 'مصروفات أسبوعية' : 'Weekly Expenses' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#breakEvenTab" data-fn="loadBreakEven">
                <i class="fas fa-scale-balanced me-1"></i>{{ app()->getLocale() === 'ar' ? 'نقطة التعادل' : 'Break-Even' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#kpiTab" data-fn="loadKpi">
                <i class="fas fa-gauge-high me-1"></i>{{ app()->getLocale() === 'ar' ? 'مؤشرات الأداء' : 'KPI Dashboard' }}
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplierRatingTab" data-fn="loadSupplierRating">
                <i class="fas fa-star me-1"></i>{{ app()->getLocale() === 'ar' ? 'تقييم الموردين' : 'Supplier Rating' }}
            </button>
        </li>
    </ul>

    <div class="tab-content">
        {{-- Sales Report --}}
        <div class="tab-pane fade show active" id="salesTab">
            <!-- Your existing sales report HTML -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="salesStart" value="{{ date('Y-m-01') }}" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="salesEnd" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.payment_method') }}</label>
                            <select class="form-select" id="salesPayment">
                                <option value="">{{ __('pos.filter') }} - All</option>
                                <option value="cash">{{ __('pos.cash') }}</option>
                                <option value="card">{{ __('pos.card') }}</option>
                                <option value="transfer">{{ __('pos.transfer') }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100 mb-2" data-fn="loadSalesReport">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                            <div class="btn-group w-100">
                                <button class="btn btn-sm btn-outline-success flex-fill" data-export-type="sales" data-export-format="csv">
                                    <i class="fas fa-file-csv me-1"></i>{{ __('pos.export_csv') }}
                                </button>
                                <button class="btn btn-sm btn-outline-danger flex-fill" data-export-type="sales" data-export-format="pdf">
                                    <i class="fas fa-file-pdf me-1"></i>{{ __('pos.export_pdf') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="row g-3 mb-4 d-none" id="salesStats">
                <div class="col-md-3">
                    <div class="stat-card blue">
                        <p class="mb-1 opacity-75 small">{{ __('pos.total') }}</p>
                        <h4 class="mb-0" id="statTotal">-</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card green">
                        <p class="mb-1 opacity-75 small">{{ __('pos.invoice_number') }}</p>
                        <h4 class="mb-0" id="statCount">-</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card orange">
                        <p class="mb-1 opacity-75 small">{{ __('pos.cash') }}</p>
                        <h4 class="mb-0" id="statCash">-</h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card purple">
                        <p class="mb-1 opacity-75 small">{{ __('pos.card') }}</p>
                        <h4 class="mb-0" id="statCard">-</h4>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Invoices table -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">{{ __('pos.recent_invoices') }}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive table-scroll-400">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>{{ __('pos.invoice_number') }}</th>
                                            <th>{{ __('pos.total') }}</th>
                                            <th>{{ __('pos.discount') }}</th>
                                            <th>{{ __('pos.final') }}</th>
                                            <th>{{ __('pos.payment_method') }}</th>
                                            <th>{{ __('pos.date') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="salesInvoicesBody">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">{{ __('pos.filter') }}
                                                to load</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top products -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">{{ __('pos.top_products') }}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>{{ __('pos.product_name') }}</th>
                                            <th>{{ __('pos.qty') }}</th>
                                            <th>{{ __('pos.total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="salesTopBody">
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Returns Report Tab --}}
        <div class="tab-pane fade" id="returnsTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="returnsStart" value="{{ date('Y-m-01') }}" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="returnsEnd" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.status') }} </label>
                            <select class="form-select" id="returnsStatus">
                                <option value="">{{ __('pos.filter') }} - All</option>
                                <option value="completed">{{ __('pos.completed') }}</option>
                                <option value="cancelled">{{ __('pos.cancelled') }} </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100 mb-2" data-fn="loadReturnsReport">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                            <div class="btn-group w-100">
                                <button class="btn btn-sm btn-outline-success flex-fill" data-export-type="returns" data-export-format="csv">
                                    <i class="fas fa-file-csv me-1"></i>{{ __('pos.export_csv') }}
                                </button>
                                <button class="btn btn-sm btn-outline-danger flex-fill" data-export-type="returns" data-export-format="pdf">
                                    <i class="fas fa-file-pdf me-1"></i>{{ __('pos.export_pdf') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Returns Summary Stats --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card blue">
                        <p class="mb-1 opacity-75 small">{{ __('pos.total_returned_value') }}</p>
                        <h4 class="mb-0" id="returnsTotal">-</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card orange">
                        <p class="mb-1 opacity-75 small">{{ __('pos.total_returns_count') }}</p>
                        <h4 class="mb-0" id="returnsCount">-</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card purple">
                        <p class="mb-1 opacity-75 small">{{ __('pos.avg_return_value') }}</p>
                        <h4 class="mb-0" id="returnsAvg">-</h4>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                {{-- Returns table --}}
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">{{ __('pos.returns_list') }}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive table-scroll-400">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>{{ __('pos.return_number') }}</th>
                                            <th>{{ __('pos.invoice_number') }}</th>
                                            <th>{{ __('pos.Customer') }}</th>
                                            <th>{{ __('pos.total_amount') }}</th>
                                            <th>{{ __('pos.reason') }}</th>
                                            <th>{{ __('pos.status') }}</th>
                                            <th>{{ __('pos.return_date') }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="returnsBody">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                {{ __('pos.select_date_range') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Top returned products --}}
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">{{ __('pos.top_returned_products') }}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>{{ __('pos.product_name') }}</th>
                                            <th>{{ __('pos.quantity') }}</th>
                                            <th>{{ __('pos.total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="returnsTopBody">
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-3">-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stock Report --}}
        <div class="tab-pane fade" id="stockTab">
            <div class="row g-3 mb-3" id="stockStats">
                <div class="col-md-4">
                    <div class="stat-card blue">
                        <p class="mb-1 opacity-75 small">{{ __('pos.total_stock_value') }}</p>
                        <h4 class="mb-0" id="stockTotalVal">-</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card orange">
                        <p class="mb-1 opacity-75 small">{{ __('pos.low_stock') }}</p>
                        <h4 class="mb-0" id="stockLowCount">-</h4>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card red">
                        <p class="mb-1 opacity-75 small">{{ __('pos.out_of_stock') }}</p>
                        <h4 class="mb-0" id="stockOutCount">-</h4>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ __('pos.stock_report') }}</span>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm input-w-180" id="stockSearch"
                            placeholder="{{ __('pos.search') }}..." data-on-input="filterStock">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-success" data-export-type="stock" data-export-format="csv">
                                <i class="fas fa-file-csv me-1"></i>{{ __('pos.export_csv') }}
                            </button>
                            <button class="btn btn-sm btn-outline-danger" data-export-type="stock" data-export-format="pdf">
                                <i class="fas fa-file-pdf me-1"></i>{{ __('pos.export_pdf') }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>{{ __('pos.product_name') }}</th>
                                    <th>{{ __('pos.category') }}</th>
                                    <th>{{ __('pos.unit') }}</th>
                                    <th>{{ __('pos.current_stock') }}</th>
                                    <th>{{ __('pos.cost_price') }}</th>
                                    <th>{{ __('pos.selling_price') }}</th>
                                    <th>{{ __('pos.stock_value') }}</th>
                                    <th>{{ __('pos.status') }}</th>
                                </tr>
                            </thead>
                            <tbody id="stockBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monthly Tax Report --}}
        <div class="tab-pane fade" id="taxMonthlyTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ app()->getLocale() === 'ar' ? 'السنة' : 'Year' }}</label>
                            <input type="number" class="form-control" id="taxYear" value="{{ date('Y') }}" min="2020" max="2099">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" data-fn="loadTaxMonthlyReport">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-3" id="taxMonthlyTotals">
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small mb-1">{{ app()->getLocale() === 'ar' ? 'إجمالي الوعاء الضريبي' : 'Total Taxable Amount' }}</div>
                            <div class="fs-5 fw-bold text-primary" id="taxTotalBase">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small mb-1">{{ app()->getLocale() === 'ar' ? 'ضريبة المبيعات (مخرجات)' : 'Output Tax (Sales)' }}</div>
                            <div class="fs-5 fw-bold text-warning" id="taxTotalCollected">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small mb-1">{{ app()->getLocale() === 'ar' ? 'ضريبة المشتريات (مدخلات)' : 'Input Tax (Purchases)' }}</div>
                            <div class="fs-5 fw-bold text-info" id="taxTotalInput">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body">
                            <div class="text-muted small mb-1">{{ app()->getLocale() === 'ar' ? 'صافي الضريبة المستحقة' : 'Net Tax Payable' }}</div>
                            <div class="fs-5 fw-bold text-danger" id="taxTotalNet">—</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>{{ app()->getLocale() === 'ar' ? 'الشهر' : 'Month' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الوعاء الضريبي' : 'Taxable Amount' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'ضريبة المبيعات' : 'Output Tax' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'ضريبة المشتريات' : 'Input Tax' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'صافي الضريبة' : 'Net Tax Payable' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإيراد الإجمالي' : 'Gross Revenue' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'عدد الفواتير' : 'Invoices' }}</th>
                                </tr>
                            </thead>
                            <tbody id="taxMonthlyBody">
                                <tr><td colspan="7" class="text-center text-muted py-4">{{ app()->getLocale() === 'ar' ? 'اختر السنة وانقر بحث' : 'Select year and click filter' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Revenue Monitoring --}}
        <div class="tab-pane fade" id="revenueMonTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="revStart" value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="revEnd" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ app()->getLocale() === 'ar' ? 'تجميع حسب' : 'Group By' }}</label>
                            <select class="form-select" id="revGroupBy">
                                <option value="day">{{ app()->getLocale() === 'ar' ? 'يوم' : 'Day' }}</option>
                                <option value="week">{{ app()->getLocale() === 'ar' ? 'أسبوع' : 'Week' }}</option>
                                <option value="month">{{ app()->getLocale() === 'ar' ? 'شهر' : 'Month' }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" data-fn="loadRevenueMonitoring">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-3" id="revTotalsRow">
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'إجمالي الإيراد' : 'Gross Revenue' }}</div>
                            <div class="fw-bold text-primary" id="revTotalGross">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'الخصومات' : 'Discounts' }}</div>
                            <div class="fw-bold text-danger" id="revTotalDiscount">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'الضريبة' : 'Tax' }}</div>
                            <div class="fw-bold text-warning" id="revTotalTax">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card text-center border-0 shadow-sm">
                        <div class="card-body py-3">
                            <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'الإيراد الصافي' : 'Net Revenue' }}</div>
                            <div class="fw-bold text-success" id="revTotalNet">—</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>{{ app()->getLocale() === 'ar' ? 'الفترة' : 'Period' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإيراد الإجمالي' : 'Gross Revenue' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الخصومات' : 'Discounts' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الضريبة' : 'Tax' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإيراد الصافي' : 'Net Revenue' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الفواتير' : 'Invoices' }}</th>
                                </tr>
                            </thead>
                            <tbody id="revMonBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">{{ app()->getLocale() === 'ar' ? 'اختر النطاق الزمني وانقر بحث' : 'Select date range and click filter' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Net Profit Report --}}
        <div class="tab-pane fade" id="netProfitTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="npStart" value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="npEnd" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" data-fn="loadNetProfit">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-success w-100" data-export-type="net-profit">
                                <i class="fas fa-file-excel me-1"></i>{{ __('pos.export_csv') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="npBelowTarget" class="alert alert-warning d-none">
                <i class="fas fa-triangle-exclamation me-1"></i>
                <span id="npBelowTargetMsg"></span>
            </div>
            <div id="npComparisonPeriod" class="text-muted small mb-2 d-none">
                <i class="fas fa-clock-rotate-left me-1"></i><span id="npComparisonPeriodText"></span>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'صافي المبيعات' : 'Net Sales' }}</div>
                    <div class="fw-bold text-primary" id="npNetSales">—</div>
                    <div id="npNetSalesChange"></div>
                </div></div></div>
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'تكلفة البضاعة' : 'COGS' }}</div>
                    <div class="fw-bold text-danger" id="npCogs">—</div>
                    <div id="npCogsChange"></div>
                </div></div></div>
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'مجمل الربح' : 'Gross Profit' }}</div>
                    <div class="fw-bold text-success" id="npGrossProfit">—</div>
                    <div class="text-muted small" id="npGrossMarginPct"></div>
                    <div id="npGrossProfitChange"></div>
                </div></div></div>
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'صافي الربح' : 'Net Profit' }}</div>
                    <div class="fw-bold text-success fs-5" id="npNetProfit">—</div>
                    <div class="text-muted small" id="npNetMarginPct"></div>
                    <div id="npNetProfitChange"></div>
                </div></div></div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody id="npBreakdown">
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ app()->getLocale() === 'ar' ? 'اختر الفترة وانقر بحث' : 'Select period and click filter' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Most Profitable Products --}}
        <div class="tab-pane fade" id="profitableProductsTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="ppStart" value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="ppEnd" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">{{ app()->getLocale() === 'ar' ? 'الحد الأقصى' : 'Limit' }}</label>
                            <select class="form-select" id="ppLimit">
                                <option value="10">10</option>
                                <option value="20" selected>20</option>
                                <option value="50">50</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" data-fn="loadProfitableProducts">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-success w-100" data-export-type="profitable-products">
                                <i class="fas fa-file-excel me-1"></i>{{ __('pos.export_csv') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>{{ app()->getLocale() === 'ar' ? 'المنتج' : 'Product' }}</th>
                                    <th>{{ app()->getLocale() === 'ar' ? 'الفئة' : 'Category' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الكمية' : 'Qty' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإيراد' : 'Revenue' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'التكلفة' : 'Cost' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الربح' : 'Profit' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'هامش %' : 'Margin %' }}</th>
                                </tr>
                            </thead>
                            <tbody id="ppBody">
                                <tr><td colspan="8" class="text-center text-muted py-4">{{ app()->getLocale() === 'ar' ? 'اختر الفترة وانقر بحث' : 'Select period and click filter' }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Weekly Expenses --}}
        <div class="tab-pane fade" id="weeklyExpensesTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="weStart" value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="weEnd" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" data-fn="loadWeeklyExpenses">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'إجمالي المصروفات' : 'Total Expenses' }}</div>
                    <div class="fw-bold text-danger fs-5" id="weTotal">—</div>
                </div></div></div>
            </div>
            <div class="row g-3">
                <div class="col-md-5">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ app()->getLocale() === 'ar' ? 'حسب الفئة' : 'By Category' }}</div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light"><tr>
                                    <th>{{ app()->getLocale() === 'ar' ? 'الفئة' : 'Category' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإجمالي' : 'Total' }}</th>
                                    <th class="text-end">{{ app()->getLocale() === 'ar' ? 'العدد' : 'Count' }}</th>
                                </tr></thead>
                                <tbody id="weCategoryBody">
                                    <tr><td colspan="3" class="text-center text-muted py-3">—</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card h-100">
                        <div class="card-header fw-semibold">{{ app()->getLocale() === 'ar' ? 'تفصيل أسبوعي' : 'Weekly Breakdown' }}</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light"><tr>
                                        <th>{{ app()->getLocale() === 'ar' ? 'الأسبوع' : 'Week' }}</th>
                                        <th class="text-end">{{ app()->getLocale() === 'ar' ? 'الإجمالي' : 'Total' }}</th>
                                        <th>{{ app()->getLocale() === 'ar' ? 'التفاصيل' : 'Details' }}</th>
                                    </tr></thead>
                                    <tbody id="weWeeksBody">
                                        <tr><td colspan="3" class="text-center text-muted py-3">—</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Break-Even Report --}}
        <div class="tab-pane fade" id="breakEvenTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="beStart" value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="beEnd" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" data-fn="loadBreakEven">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="beProfitableAlert" class="d-none"></div>
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'نقطة التعادل (إيراد)' : 'Break-Even Revenue' }}</div>
                    <div class="fw-bold text-warning fs-5" id="beRevenue">—</div>
                </div></div></div>
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'نقطة التعادل (يومي)' : 'Daily Break-Even' }}</div>
                    <div class="fw-bold text-info" id="beDaily">—</div>
                </div></div></div>
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'عدد الفواتير للتعادل' : 'Orders for Break-Even' }}</div>
                    <div class="fw-bold" id="beOrders">—</div>
                </div></div></div>
                <div class="col-6 col-md-3"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'هامش الأمان' : 'Margin of Safety' }}</div>
                    <div class="fw-bold text-success" id="beSafety">—</div>
                </div></div></div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <tbody id="beBreakdown">
                            <tr><td colspan="2" class="text-center text-muted py-4">{{ app()->getLocale() === 'ar' ? 'اختر الفترة وانقر بحث' : 'Select period and click filter' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- KPI Dashboard --}}
        <div class="tab-pane fade" id="kpiTab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center gap-3">
                    <input type="date" class="form-control form-control-sm input-w-180" id="kpiDate" value="{{ date('Y-m-d') }}">
                    <button class="btn btn-sm btn-primary" data-fn="loadKpi"><i class="fas fa-rotate me-1"></i>{{ app()->getLocale() === 'ar' ? 'تحديث' : 'Refresh' }}</button>
                </div>
                <div id="kpiLastUpdated" class="text-muted small"></div>
            </div>
            <div id="kpiAlerts"></div>
            <h6 class="text-muted fw-semibold mb-2">{{ app()->getLocale() === 'ar' ? 'اليوم' : "Today's Performance" }}</h6>
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-2"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'المبيعات' : 'Sales' }}</div>
                    <div class="fw-bold text-primary" id="kpiRevenue">—</div>
                </div></div></div>
                <div class="col-6 col-md-2"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'الفواتير' : 'Invoices' }}</div>
                    <div class="fw-bold" id="kpiInvoices">—</div>
                </div></div></div>
                <div class="col-6 col-md-2"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'متوسط الفاتورة' : 'Avg Invoice' }}</div>
                    <div class="fw-bold" id="kpiAvg">—</div>
                </div></div></div>
                <div class="col-6 col-md-2"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'مجمل الربح' : 'Gross Profit' }}</div>
                    <div class="fw-bold text-success" id="kpiGross">—</div>
                    <div class="text-muted small" id="kpiGrossPct"></div>
                </div></div></div>
                <div class="col-6 col-md-2"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'صافي الربح' : 'Net Profit' }}</div>
                    <div class="fw-bold text-success" id="kpiNet">—</div>
                </div></div></div>
                <div class="col-6 col-md-2"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'المرتجعات' : 'Returns' }}</div>
                    <div class="fw-bold text-danger" id="kpiReturns">—</div>
                </div></div></div>
            </div>
            <h6 class="text-muted fw-semibold mb-2">{{ app()->getLocale() === 'ar' ? 'الشهر الحالي' : 'This Month' }}</h6>
            <div class="row g-3">
                <div class="col-6 col-md-4"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'إجمالي المبيعات' : 'Total Sales' }}</div>
                    <div class="fw-bold text-primary" id="kpiMonthRevenue">—</div>
                </div></div></div>
                <div class="col-6 col-md-4"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'عدد الفواتير' : 'Invoice Count' }}</div>
                    <div class="fw-bold" id="kpiMonthInvoices">—</div>
                </div></div></div>
                <div class="col-6 col-md-4"><div class="card text-center border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small">{{ app()->getLocale() === 'ar' ? 'متوسط الفاتورة' : 'Avg Invoice' }}</div>
                    <div class="fw-bold" id="kpiMonthAvg">—</div>
                </div></div></div>
            </div>
        </div>

        {{-- Supplier Rating --}}
        <div class="tab-pane fade" id="supplierRatingTab">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.start_date') }}</label>
                            <input type="date" class="form-control" id="srStart" value="{{ date('Y-m-01') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('pos.end_date') }}</label>
                            <input type="date" class="form-control" id="srEnd" value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" data-fn="loadSupplierRating">
                                <i class="fas fa-search me-1"></i>{{ __('pos.filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ app()->getLocale() === 'ar' ? 'المورد' : 'Supplier' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'إجمالي الطلبات' : 'Total POs' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'مستلمة' : 'Received' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'ملغاة' : 'Cancelled' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'معدل التسليم في الوقت' : 'On-Time %' }}</th>
                                <th class="text-center">{{ app()->getLocale() === 'ar' ? 'متوسط وقت التوريد (أيام)' : 'Avg Lead (days)' }}</th>
                                <th class="text-end">{{ app()->getLocale() === 'ar' ? 'إجمالي المشتريات' : 'Total Value' }}</th>
                            </tr>
                        </thead>
                        <tbody id="srBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ app()->getLocale() === 'ar' ? 'اختر الفترة وانقر بحث' : 'Select period and click filter' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script @nonce>
        const EXPORT_URLS = {
            sales:                '{{ route('reports.export.sales') }}',
            returns:              '{{ route('reports.export.returns') }}',
            stock:                '{{ route('reports.export.stock') }}',
            'net-profit':         '{{ route('reports.net-profit.export') }}',
            'profitable-products':'{{ route('reports.profitable-products.export') }}',
        };

        let stockData = [];
        let salesInvoiceMap = {};
        let returnsMap = {};

        async function loadSalesReport() {
            const start = document.getElementById('salesStart').value;
            const end = document.getElementById('salesEnd').value;
            const payment = document.getElementById('salesPayment').value;

            const res = await apiCall('{{ route('reports.sales') }}', 'POST', {
                start_date: start,
                end_date: end,
                payment_method: payment || undefined
            });

            // Show stats
            document.getElementById('salesStats').classList.remove('d-none');
            document.getElementById('statTotal').textContent = formatCurrency(res.totals?.total_revenue);
            document.getElementById('statCount').textContent = res.totals?.total_count ?? 0;
            document.getElementById('statCash').textContent = formatCurrency(res.byPayment?.cash?.total || 0);
            document.getElementById('statCard').textContent = formatCurrency(res.byPayment?.card?.total || 0);

            // Invoices
            const invoiceList = res.invoices?.data || [];
            salesInvoiceMap = {};
            invoiceList.forEach(inv => salesInvoiceMap[inv.id] = inv);
            document.getElementById('salesInvoicesBody').innerHTML = invoiceList.length ?
                invoiceList.map(inv => `
            <tr>
                <td><span class="badge bg-primary">${inv.invoice_number}</span></td>
                <td>${formatCurrency(inv.total)}</td>
                <td class="text-danger">${inv.discount > 0 ? '-'+formatCurrency(inv.discount) : '-'}</td>
                <td class="fw-semibold">${formatCurrency(inv.final_total)}</td>
                <td><span class="badge bg-secondary">${inv.payment_method}</span></td>
                <td class="text-muted small">${formatDate(inv.created_at)}</td>
                <td><button class="btn btn-xs btn-outline-secondary py-0 px-1" data-print-type="invoice" data-print-id="${inv.id}" title="{{ __('pos.print') }}"><i class="fas fa-print"></i></button></td>
            </tr>`).join('') :
                '<tr><td colspan="7" class="text-center text-muted py-3">{{ __('pos.no_data') }}</td></tr>';

            // Top products
            document.getElementById('salesTopBody').innerHTML = (res.topProducts || []).length ?
                res.topProducts.map((p, i) => `
            <tr>
                <td><span class="badge bg-secondary me-1">${i+1}</span>${p.product_name}</td>
                <td>${p.total_qty}</td>
                <td>${formatCurrency(p.total_sales)}</td>
            </tr>`).join('') :
                '<tr><td colspan="3" class="text-center text-muted py-3">-</td></tr>';
        }

        async function loadReturnsReport() {
            const start = document.getElementById('returnsStart').value;
            const end = document.getElementById('returnsEnd').value;
            const status = document.getElementById('returnsStatus').value;

            const res = await apiCall('{{ route('reports.returns') }}', 'POST', {
                start_date: start,
                end_date: end,
                status: status || undefined
            });

            // Update stats
            const retTotal = res.totals?.total_returned ?? 0;
            const retCount = res.totals?.total_count ?? 0;
            document.getElementById('returnsTotal').textContent = formatCurrency(retTotal);
            document.getElementById('returnsCount').textContent = retCount;
            document.getElementById('returnsAvg').textContent = formatCurrency(retCount > 0 ? retTotal / retCount : 0);

            // Returns table
            const returnList = res.returns?.data || [];
            returnsMap = {};
            returnList.forEach(ret => returnsMap[ret.id] = ret);
            document.getElementById('returnsBody').innerHTML = returnList.length ?
                returnList.map(ret => `
            <tr>
                <td><span class="badge bg-danger">${ret.return_number}</span></td>
                <td>${ret.invoice_number || '-'}</td>
                <td>${ret.customer_name || 'Walk-in'}</td>
                <td>${formatCurrency(ret.total_amount)}</td>
                <td>${ret.reason || '-'}</td>
                <td><span class="badge ${ret.status === 'completed' ? 'bg-success' : 'bg-secondary'}">${ret.status}</span></td>
                <td class="text-muted small">${formatDate(ret.return_date)}</td>
                <td><button class="btn btn-xs btn-outline-secondary py-0 px-1" data-print-type="return" data-print-id="${ret.id}" title="{{ __('pos.print') }}"><i class="fas fa-print"></i></button></td>
            </tr>`).join('') :
                '<tr><td colspan="8" class="text-center text-muted py-3">{{ __('pos.no_data') }}</td></tr>';


            // Top returned products
            document.getElementById('returnsTopBody').innerHTML = (res.topReturnedProducts || []).length ?
                res.topReturnedProducts.map((p, i) => `
            <tr>
                <td><span class="badge bg-secondary me-1">${i+1}</span>${p.product_name}</td>
                <td class="text-danger">${p.total_qty}</td>
                <td class="text-danger">${formatCurrency(p.total_amount)}</td>
            </tr>`).join('') :
                '<tr><td colspan="3" class="text-center text-muted py-3">-</td></tr>';
        }

        async function loadStockReport() {
            const res = await apiCall('{{ route('reports.stock') }}');
            stockData = res.products || [];

            document.getElementById('stockTotalVal').textContent = formatCurrency(res.total_stock_value);
            document.getElementById('stockLowCount').textContent = res.low_stock_count;
            document.getElementById('stockOutCount').textContent = res.out_of_stock;

            renderStock(stockData);
        }

        function filterStock() {
            const q = document.getElementById('stockSearch').value.toLowerCase();
            renderStock(stockData.filter(p => p.name.toLowerCase().includes(q) || (p.category || '').toLowerCase().includes(
                q)));
        }

        function escapeHtml(str) {
            if (str == null) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }

        function renderStock(products) {
            document.getElementById('stockBody').innerHTML = products.length ?
                products.map(p => `
            <tr>
                <td class="fw-semibold">${p.name}</td>
                <td>${p.category || '-'}</td>
                <td><span class="badge bg-info text-dark">${p.unit_abbreviation || p.unit_name || '-'}</span></td>
                <td class="fw-bold ${p.quantity === 0 ? 'text-danger' : p.low_stock ? 'text-warning' : 'text-success'}">${p.quantity}</td>
                <td>${formatCurrency(p.cost_price)}</td>
                <td>${formatCurrency(p.price)}</td>
                <td>${formatCurrency(p.stock_value)}</td>
                <td>${p.quantity === 0
                    ? '<span class="badge bg-danger">{{ __('pos.out_of_stock') }}</span>'
                    : p.low_stock
                    ? '<span class="badge badge-low-stock">{{ __('pos.low_stock') }}</span>'
                    : '<span class="badge badge-in-stock">OK</span>'}</td>
            </tr>`).join('') :
                '<tr><td colspan="8" class="text-center text-muted py-4">{{ __('pos.no_data') }}</td></tr>';
        }
        function validateDateRange(startId, endId) {
            const s = new Date(document.getElementById(startId).value);
            const e = new Date(document.getElementById(endId).value);
            const diffDays = (e - s) / (1000 * 60 * 60 * 24);
            if (diffDays > 365) {
                alert({{ app()->getLocale() === 'ar' ? '"النطاق الزمني لا يتجاوز سنة واحدة."' : '"Date range cannot exceed one year."' }});
                return false;
            }
            return true;
        }

        document.addEventListener('click', function (e) {
            const exportBtn = e.target.closest('[data-export-type]');
            if (exportBtn) {
                const type   = exportBtn.dataset.exportType;
                const format = exportBtn.dataset.exportFormat;
                const params = new URLSearchParams({ format });
                if (type === 'sales') {
                    if (!validateDateRange('salesStart', 'salesEnd')) return;
                    params.set('start_date', document.getElementById('salesStart').value);
                    params.set('end_date',   document.getElementById('salesEnd').value);
                    const pay = document.getElementById('salesPayment').value;
                    if (pay) params.set('payment_method', pay);
                } else if (type === 'returns') {
                    if (!validateDateRange('returnsStart', 'returnsEnd')) return;
                    params.set('start_date', document.getElementById('returnsStart').value);
                    params.set('end_date',   document.getElementById('returnsEnd').value);
                    const st = document.getElementById('returnsStatus').value;
                    if (st) params.set('status', st);
                } else if (type === 'net-profit') {
                    params.set('start_date', document.getElementById('npStart').value);
                    params.set('end_date',   document.getElementById('npEnd').value);
                } else if (type === 'profitable-products') {
                    params.set('start_date', document.getElementById('ppStart').value);
                    params.set('end_date',   document.getElementById('ppEnd').value);
                    params.set('limit',      document.getElementById('ppLimit').value);
                }
                const url = EXPORT_URLS[type] + '?' + params;
                format === 'pdf' ? window.open(url, '_blank') : (window.location.href = url);
                return;
            }

            const printBtn = e.target.closest('[data-print-type]');
            if (printBtn) {
                const type = printBtn.dataset.printType;
                const id   = printBtn.dataset.printId;
                if (type === 'invoice') {
                    const inv = salesInvoiceMap[id];
                    if (inv) openPrintWindow(generateInvoicePrintHtml(inv));
                } else if (type === 'return') {
                    const ret = returnsMap[id];
                    if (ret) openPrintWindow(generateReturnPrintHtml(ret));
                }
            }
        });

        function openPrintWindow(html) {
            const w = window.open('', '_blank', 'width=420,height=700');
            w.document.write(html);
            w.document.close();
            w.focus();
            w.print();
            w.onafterprint = () => w.close();
        }

        function receiptStyles(dir) {
            const align = dir === 'rtl' ? 'right' : 'left';
            return `
                body{font-family:'Cairo','Segoe UI',Tahoma,sans-serif;font-size:13px;line-height:1.4;margin:0;padding:15px;background:#fff;max-width:350px;margin:0 auto}
                .box{border:1px solid #ddd;padding:12px;border-radius:5px}
                .hdr{text-align:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px dashed #aaa}
                .store{font-size:17px;font-weight:bold}
                .title{font-size:13px;font-weight:bold;margin-top:4px}
                .row{display:flex;justify-content:space-between;margin:3px 0;font-size:12px}
                table{width:100%;border-collapse:collapse;margin:10px 0}
                th{background:#f2f2f2;padding:5px 4px;font-size:11px;border-bottom:1px solid #aaa;text-align:${align}}
                td{padding:5px 4px;border-bottom:1px solid #eee;font-size:12px}
                .totals{border-top:1px solid #ccc;margin-top:4px}
                .grand{font-weight:bold;border-top:2px solid #333}
                .ftr{text-align:center;margin-top:12px;font-size:11px;color:#555;border-top:1px dashed #aaa;padding-top:8px}
                @media print{body{padding:0}.box{border:none;padding:0}}`;
        }

        function generateInvoicePrintHtml(inv) {
            const isRTL  = document.documentElement.dir === 'rtl';
            const dir    = isRTL ? 'rtl' : 'ltr';
            const rAlign = 'right';
            const lAlign = isRTL ? 'right' : 'left';
            const date   = inv.created_at ? new Date(inv.created_at).toLocaleString(isRTL ? 'ar-EG' : 'en-EG') : '';
            const items  = Array.isArray(inv.items) ? inv.items : [];

            const itemRows = items.map(it => `
                <tr>
                    <td style="text-align:${lAlign}">${escapeHtml(it.product_name)}</td>
                    <td style="text-align:center">${it.quantity}</td>
                    <td style="text-align:${rAlign}">${formatCurrency(it.price)}</td>
                    <td style="text-align:${rAlign}">${formatCurrency(it.subtotal)}</td>
                </tr>`).join('');

            const discountRow = inv.discount > 0 ? `
                <tr><td colspan="3" style="text-align:${rAlign};color:#d9534f">${isRTL?'الخصم':'Discount'}</td>
                <td style="text-align:${rAlign};color:#d9534f">-${formatCurrency(inv.discount)}</td></tr>` : '';

            const taxRow = inv.tax_amount > 0 ? `
                <tr><td colspan="3" style="text-align:${rAlign}">${isRTL?'الضريبة':'Tax'}</td>
                <td style="text-align:${rAlign}">${formatCurrency(inv.tax_amount)}</td></tr>` : '';

            const cashRows = inv.payment_method === 'cash' && inv.cash_received != null ? `
                <tr><td colspan="3" style="text-align:${rAlign};font-weight:bold">${isRTL?'المدفوع':'Paid'}</td>
                <td style="text-align:${rAlign};color:#198754;font-weight:bold">${formatCurrency(inv.cash_received)}</td></tr>
                <tr style="background:#fff3cd"><td colspan="3" style="text-align:${rAlign};font-weight:bold">${isRTL?'الباقي':'Change'}</td>
                <td style="text-align:${rAlign};font-weight:bold;color:#856404">${formatCurrency(inv.change_amount ?? 0)}</td></tr>` : '';

            return `<!DOCTYPE html><html dir="${dir}"><head><meta charset="utf-8">
                <title>${inv.invoice_number}</title><style>${receiptStyles(dir)}</style></head><body>
                <div class="box">
                    <div class="hdr">
                        <div class="store">{{ __('pos.sales_report') }}</div>
                        <div class="title">${isRTL?'رقم الفاتورة':'Invoice No'}: ${escapeHtml(inv.invoice_number)}</div>
                    </div>
                    <div class="row"><span>${isRTL?'التاريخ':'Date'}:</span><span>${escapeHtml(date)}</span></div>
                    <div class="row"><span>${isRTL?'أمين الصندوق':'Cashier'}:</span><span>${escapeHtml(inv.cashier_name||'-')}</span></div>
                    <table>
                        <thead><tr>
                            <th>${isRTL?'المنتج':'Product'}</th>
                            <th style="text-align:center">${isRTL?'الكمية':'Qty'}</th>
                            <th style="text-align:${rAlign}">${isRTL?'السعر':'Price'}</th>
                            <th style="text-align:${rAlign}">${isRTL?'الإجمالي':'Total'}</th>
                        </tr></thead>
                        <tbody>${itemRows}</tbody>
                    </table>
                    <table class="totals">
                        <tr><td colspan="3" style="text-align:${rAlign}">${isRTL?'المجموع الفرعي':'Subtotal'}</td>
                            <td style="text-align:${rAlign}">${formatCurrency(inv.total)}</td></tr>
                        ${discountRow}${taxRow}
                        <tr class="grand"><td colspan="3" style="text-align:${rAlign}">${isRTL?'الإجمالي النهائي':'Grand Total'}</td>
                            <td style="text-align:${rAlign}">${formatCurrency(inv.final_total)}</td></tr>
                        ${cashRows}
                    </table>
                    <div class="row"><span>${isRTL?'طريقة الدفع':'Payment'}:</span><span>${escapeHtml(inv.payment_method||'-')}</span></div>
                    <div class="ftr"><div style="font-weight:bold">${isRTL?'شكراً لتسوقكم معنا':'Thank you for shopping with us'}</div></div>
                </div></body></html>`;
        }

        function generateReturnPrintHtml(ret) {
            const isRTL  = document.documentElement.dir === 'rtl';
            const dir    = isRTL ? 'rtl' : 'ltr';
            const rAlign = 'right';
            const lAlign = isRTL ? 'right' : 'left';
            const date   = ret.return_date ? new Date(ret.return_date).toLocaleDateString(isRTL ? 'ar-EG' : 'en-EG') : '';
            const items  = Array.isArray(ret.items) ? ret.items : [];

            const itemRows = items.map(it => `
                <tr>
                    <td style="text-align:${lAlign}">${escapeHtml(it.product_name)}</td>
                    <td style="text-align:center">${it.quantity}</td>
                    <td style="text-align:${rAlign}">${formatCurrency(it.price)}</td>
                    <td style="text-align:${rAlign}">${formatCurrency(it.subtotal)}</td>
                </tr>`).join('');

            return `<!DOCTYPE html><html dir="${dir}"><head><meta charset="utf-8">
                <title>${ret.return_number}</title><style>${receiptStyles(dir)}</style></head><body>
                <div class="box">
                    <div class="hdr">
                        <div class="store">{{ __('pos.Returns Report') }}</div>
                        <div class="title">${isRTL?'رقم المرتجع':'Return No'}: ${escapeHtml(ret.return_number)}</div>
                    </div>
                    <div class="row"><span>${isRTL?'الفاتورة الأصلية':'Original Invoice'}:</span><span>${escapeHtml(ret.invoice_number||'-')}</span></div>
                    <div class="row"><span>${isRTL?'العميل':'Customer'}:</span><span>${escapeHtml(ret.customer_name||'Walk-in')}</span></div>
                    <div class="row"><span>${isRTL?'التاريخ':'Date'}:</span><span>${escapeHtml(date)}</span></div>
                    ${ret.reason ? `<div class="row"><span>${isRTL?'السبب':'Reason'}:</span><span>${escapeHtml(ret.reason)}</span></div>` : ''}
                    <table>
                        <thead><tr>
                            <th>${isRTL?'المنتج':'Product'}</th>
                            <th style="text-align:center">${isRTL?'الكمية':'Qty'}</th>
                            <th style="text-align:${rAlign}">${isRTL?'السعر':'Price'}</th>
                            <th style="text-align:${rAlign}">${isRTL?'الإجمالي':'Total'}</th>
                        </tr></thead>
                        <tbody>${itemRows || `<tr><td colspan="4" style="text-align:center;color:#999">${isRTL?'لا توجد تفاصيل':'No items'}</td></tr>`}</tbody>
                    </table>
                    <table class="totals">
                        <tr class="grand"><td colspan="3" style="text-align:${rAlign}">${isRTL?'إجمالي المرتجع':'Return Total'}</td>
                            <td style="text-align:${rAlign}">${formatCurrency(ret.total_amount)}</td></tr>
                    </table>
                    ${ret.refund_method ? `<div class="row"><span>${isRTL?'طريقة الاسترداد':'Refund Method'}:</span><span>${escapeHtml(ret.refund_method)}</span></div>` : ''}
                    <div class="row"><span>${isRTL?'الحالة':'Status'}:</span><span>${escapeHtml(ret.status)}</span></div>
                    <div class="ftr"><div style="font-weight:bold">${isRTL?'تم استلام المرتجع بنجاح':'Return processed successfully'}</div></div>
                </div></body></html>`;
        }

        async function loadTaxMonthlyReport() {
            const year = document.getElementById('taxYear').value;
            if (!year) return;
            const res = await apiCall('{{ route('reports.tax.monthly') }}?year=' + year);
            if (!res) return;

            document.getElementById('taxTotalBase').textContent      = formatCurrency(res.totals?.taxable_amount ?? 0);
            document.getElementById('taxTotalCollected').textContent  = formatCurrency(res.totals?.tax_collected ?? 0);
            document.getElementById('taxTotalInput').textContent      = formatCurrency(res.totals?.input_tax ?? 0);
            document.getElementById('taxTotalNet').textContent        = formatCurrency(res.totals?.net_tax_payable ?? 0);

            const isRTL = document.documentElement.dir === 'rtl';
            const monthNames = isRTL
                ? ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر']
                : ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

            const months = res.months || [];
            document.getElementById('taxMonthlyBody').innerHTML = months.length
                ? months.map(m => `
                    <tr>
                        <td class="fw-semibold">${monthNames[(m.month - 1)] || m.month}</td>
                        <td>${formatCurrency(m.taxable_amount)}</td>
                        <td class="text-warning fw-semibold">${formatCurrency(m.tax_collected)}</td>
                        <td class="text-info">${formatCurrency(m.input_tax)}</td>
                        <td class="fw-bold ${m.net_tax_payable >= 0 ? 'text-danger' : 'text-success'}">${formatCurrency(m.net_tax_payable)}</td>
                        <td class="text-success">${formatCurrency(m.gross_revenue)}</td>
                        <td class="text-center">${m.invoice_count}</td>
                    </tr>`).join('')
                : `<tr><td colspan="7" class="text-center text-muted py-3">{{ __('pos.no_data') }}</td></tr>`;
        }

        async function loadRevenueMonitoring() {
            const start   = document.getElementById('revStart').value;
            const end     = document.getElementById('revEnd').value;
            const groupBy = document.getElementById('revGroupBy').value;
            if (!start || !end) return;

            const res = await apiCall('{{ route('reports.revenue-monitoring') }}', 'POST', {
                start_date: start,
                end_date:   end,
                group_by:   groupBy,
            });
            if (!res) return;

            document.getElementById('revTotalGross').textContent    = formatCurrency(res.totals?.gross_revenue ?? 0);
            document.getElementById('revTotalDiscount').textContent = formatCurrency(res.totals?.total_discount ?? 0);
            document.getElementById('revTotalTax').textContent      = formatCurrency(res.totals?.tax_collected ?? 0);
            document.getElementById('revTotalNet').textContent      = formatCurrency(res.totals?.net_revenue ?? 0);

            const rows = res.rows || [];
            document.getElementById('revMonBody').innerHTML = rows.length
                ? rows.map(r => `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(r.period)}</td>
                        <td>${formatCurrency(r.gross_revenue)}</td>
                        <td class="text-danger">${formatCurrency(r.total_discount)}</td>
                        <td class="text-warning">${formatCurrency(r.tax_collected)}</td>
                        <td class="text-success fw-semibold">${formatCurrency(r.net_revenue)}</td>
                        <td class="text-center">${r.invoice_count}</td>
                    </tr>`).join('')
                : `<tr><td colspan="6" class="text-center text-muted py-3">{{ __('pos.no_data') }}</td></tr>`;
        }

        // ── Net Profit ──────────────────────────────────────────────────────────
        function npChangeBadge(pct, elId, lowerIsBetter) {
            const el = document.getElementById(elId);
            if (!el) return;
            if (pct === null || pct === undefined) { el.innerHTML = ''; return; }
            const improved = lowerIsBetter ? pct < 0 : pct > 0;
            const cls   = improved ? 'text-success' : (pct === 0 ? 'text-muted' : 'text-danger');
            const arrow = pct > 0 ? '▲' : (pct < 0 ? '▼' : '→');
            el.innerHTML = `<span class="${cls} small">${arrow} ${Math.abs(pct)}%</span>`;
        }

        async function loadNetProfit() {
            const start = document.getElementById('npStart').value;
            const end   = document.getElementById('npEnd').value;
            if (!start || !end) return;
            const res = await apiCall('{{ route('reports.net-profit') }}', 'POST', { start_date: start, end_date: end });
            if (!res) return;

            document.getElementById('npNetSales').textContent    = formatCurrency(res.net_sales);
            document.getElementById('npCogs').textContent        = formatCurrency(res.cogs);
            document.getElementById('npGrossProfit').textContent = formatCurrency(res.gross_profit);
            document.getElementById('npGrossMarginPct').textContent = res.gross_margin_pct + '%';
            document.getElementById('npNetProfit').textContent   = formatCurrency(res.net_profit);
            document.getElementById('npNetMarginPct').textContent = res.net_margin_pct + '%';

            const cmp = res.comparison || {};
            npChangeBadge(cmp.net_sales_change_pct,          'npNetSalesChange',    false);
            npChangeBadge(cmp.cogs_change_pct,               'npCogsChange',        true);
            npChangeBadge(cmp.gross_profit_change_pct,       'npGrossProfitChange', false);
            npChangeBadge(cmp.net_profit_change_pct,         'npNetProfitChange',   false);

            const cmpWrap = document.getElementById('npComparisonPeriod');
            const cmpText = document.getElementById('npComparisonPeriodText');
            if (cmp.prev_start_date) {
                const isRTL = document.documentElement.dir === 'rtl';
                cmpText.textContent = isRTL
                    ? `مقارنة بالفترة: ${cmp.prev_start_date} – ${cmp.prev_end_date}`
                    : `vs. prev. period: ${cmp.prev_start_date} – ${cmp.prev_end_date}`;
                cmpWrap.classList.remove('d-none');
            } else {
                cmpWrap.classList.add('d-none');
            }

            const belowAlert = document.getElementById('npBelowTarget');
            const belowMsg   = document.getElementById('npBelowTargetMsg');
            if (res.below_target && res.margin_target_pct > 0) {
                belowMsg.textContent = (document.documentElement.dir === 'rtl'
                    ? `تحذير: هامش الربح الإجمالي ${res.gross_margin_pct}% أقل من المستهدف ${res.margin_target_pct}%`
                    : `Warning: Gross margin ${res.gross_margin_pct}% is below target ${res.margin_target_pct}%`);
                belowAlert.classList.remove('d-none');
            } else {
                belowAlert.classList.add('d-none');
            }

            const isRTL = document.documentElement.dir === 'rtl';
            const rows = [
                [isRTL ? 'إجمالي المبيعات'        : 'Gross Sales',          formatCurrency(res.gross_sales),                   '',                                   null],
                [isRTL ? 'الخصومات'                : 'Discounts',            `(${formatCurrency(res.discounts)})`,              'text-danger',                        null],
                [isRTL ? 'الضريبة المحصلة'         : 'Tax Collected',        `(${formatCurrency(res.tax)})`,                    'text-muted',                         null],
                [isRTL ? 'المرتجعات'               : 'Returns',              `(${formatCurrency(res.returns)})`,                'text-danger',                        null],
                [isRTL ? 'صافي الإيراد'            : 'Net Revenue',          formatCurrency(res.net_revenue),                   'fw-semibold border-top',             null],
                [isRTL ? 'تكلفة البضاعة (COGS)'   : 'COGS',                 `(${formatCurrency(res.cogs)})`,                   'text-danger',                        cmp.cogs_change_pct],
                [isRTL ? 'مجمل الربح'              : 'Gross Profit',         formatCurrency(res.gross_profit),                  'fw-semibold text-success border-top', cmp.gross_profit_change_pct],
                [isRTL ? 'المصروفات التشغيلية'     : 'Operating Expenses',   `(${formatCurrency(res.operating_expenses)})`,     'text-danger',                        cmp.operating_expenses_change_pct],
                [isRTL ? 'صافي الربح'              : 'Net Profit',           formatCurrency(res.net_profit),                    'fw-bold text-success fs-6 border-top', cmp.net_profit_change_pct],
            ];

            function inlineChange(pct, lowerIsBetter) {
                if (pct === null || pct === undefined) return '';
                const improved = lowerIsBetter ? pct < 0 : pct > 0;
                const cls   = improved ? 'text-success' : (pct === 0 ? 'text-muted' : 'text-danger');
                const arrow = pct > 0 ? '▲' : (pct < 0 ? '▼' : '→');
                return `<span class="${cls} small ms-2">${arrow}${Math.abs(pct)}%</span>`;
            }

            document.getElementById('npBreakdown').innerHTML = rows.map(([label, val, cls, pct]) => {
                const lowerBetter = label.includes('COGS') || label.includes('تكلفة') || label.includes('مصروف') || label.includes('Expenses');
                return `<tr class="${cls}"><td class="py-2">${label}</td><td class="text-end py-2">${val}${inlineChange(pct, lowerBetter)}</td></tr>`;
            }).join('');
        }

        // ── Most Profitable Products ─────────────────────────────────────────────
        async function loadProfitableProducts() {
            const start = document.getElementById('ppStart').value;
            const end   = document.getElementById('ppEnd').value;
            const limit = document.getElementById('ppLimit').value;
            if (!start || !end) return;
            const res = await apiCall('{{ route('reports.profitable-products') }}', 'POST', { start_date: start, end_date: end, limit });
            if (!res) return;

            const products = res.products || [];
            document.getElementById('ppBody').innerHTML = products.length
                ? products.map((p, i) => {
                    const barColor = p.profit_margin >= 30 ? 'bg-success' : p.profit_margin >= 15 ? 'bg-warning' : 'bg-danger';
                    return `<tr>
                        <td><span class="badge bg-secondary">${i + 1}</span></td>
                        <td class="fw-semibold">${escapeHtml(p.product_name)}</td>
                        <td>${escapeHtml(p.category || '-')}</td>
                        <td class="text-end">${p.total_qty}</td>
                        <td class="text-end">${formatCurrency(p.total_revenue)}</td>
                        <td class="text-end text-danger">${formatCurrency(p.total_cost)}</td>
                        <td class="text-end text-success fw-semibold">${formatCurrency(p.gross_profit)}</td>
                        <td class="text-end"><span class="badge ${barColor}">${p.profit_margin}%</span></td>
                    </tr>`;
                }).join('')
                : `<tr><td colspan="8" class="text-center text-muted py-4">{{ __('pos.no_data') }}</td></tr>`;
        }

        // ── Weekly Expenses ──────────────────────────────────────────────────────
        async function loadWeeklyExpenses() {
            const start = document.getElementById('weStart').value;
            const end   = document.getElementById('weEnd').value;
            if (!start || !end) return;
            const res = await apiCall('{{ route('reports.weekly-expenses') }}', 'POST', { start_date: start, end_date: end });
            if (!res) return;

            document.getElementById('weTotal').textContent = formatCurrency(res.total);

            const cats = res.by_category || [];
            document.getElementById('weCategoryBody').innerHTML = cats.length
                ? cats.map(c => `<tr>
                    <td>${escapeHtml(c.category)}</td>
                    <td class="text-end text-danger">${formatCurrency(c.total)}</td>
                    <td class="text-end">${c.count}</td>
                  </tr>`).join('')
                : `<tr><td colspan="3" class="text-center text-muted py-2">—</td></tr>`;

            const weeks = res.weeks || [];
            document.getElementById('weWeeksBody').innerHTML = weeks.length
                ? weeks.map(w => {
                    const details = (w.by_category || []).map(c => `${escapeHtml(c.category)}: ${formatCurrency(c.total)}`).join(' | ');
                    return `<tr>
                        <td class="fw-semibold">${w.week_start}</td>
                        <td class="text-end text-danger">${formatCurrency(w.total)}</td>
                        <td class="text-muted small">${details}</td>
                    </tr>`;
                }).join('')
                : `<tr><td colspan="3" class="text-center text-muted py-2">—</td></tr>`;
        }

        // ── Break-Even ───────────────────────────────────────────────────────────
        async function loadBreakEven() {
            const start = document.getElementById('beStart').value;
            const end   = document.getElementById('beEnd').value;
            if (!start || !end) return;
            const res = await apiCall('{{ route('reports.break-even') }}', 'POST', { start_date: start, end_date: end });
            if (!res) return;

            document.getElementById('beRevenue').textContent = res.break_even_revenue !== null ? formatCurrency(res.break_even_revenue) : '—';
            document.getElementById('beDaily').textContent   = res.daily_break_even !== null ? formatCurrency(res.daily_break_even) : '—';
            document.getElementById('beOrders').textContent  = res.break_even_orders !== null ? res.break_even_orders : '—';
            document.getElementById('beSafety').textContent  = res.margin_of_safety !== null ? formatCurrency(res.margin_of_safety) : '—';

            const alertEl = document.getElementById('beProfitableAlert');
            const isRTL = document.documentElement.dir === 'rtl';
            if (res.is_profitable) {
                alertEl.className = 'alert alert-success mb-3';
                alertEl.innerHTML = `<i class="fas fa-check-circle me-1"></i> ${isRTL ? 'المنشأة مربحة — المبيعات تتجاوز نقطة التعادل' : 'Profitable — sales exceed break-even point'}`;
            } else {
                alertEl.className = 'alert alert-warning mb-3';
                alertEl.innerHTML = `<i class="fas fa-triangle-exclamation me-1"></i> ${isRTL ? 'المبيعات لم تبلغ نقطة التعادل بعد' : 'Sales have not yet reached the break-even point'}`;
            }
            alertEl.classList.remove('d-none');

            const rows = [
                [isRTL ? 'الإيراد الفعلي' : 'Actual Revenue',           formatCurrency(res.revenue),            ''],
                [isRTL ? 'الإيراد (بدون ضريبة)' : 'Revenue (ex-tax)',   formatCurrency(res.revenue_ex_tax),     ''],
                [isRTL ? 'تكلفة البضاعة' : 'COGS (Variable)',           formatCurrency(res.cogs),               'text-danger'],
                [isRTL ? 'التكاليف الثابتة (مصروفات)' : 'Fixed Costs',  formatCurrency(res.fixed_costs),        'text-danger'],
                [isRTL ? 'نسبة التكلفة المتغيرة' : 'Variable Cost Ratio', res.variable_cost_ratio_pct + '%',    ''],
                [isRTL ? 'نسبة هامش المساهمة' : 'Contribution Margin',  res.contribution_margin_ratio_pct + '%','text-success'],
                [isRTL ? 'نقطة التعادل (إيراد)' : 'Break-Even Revenue',  res.break_even_revenue !== null ? formatCurrency(res.break_even_revenue) : '—', 'fw-bold border-top'],
                [isRTL ? 'نقطة التعادل (يومي)' : 'Daily Break-Even',    res.daily_break_even !== null ? formatCurrency(res.daily_break_even) : '—', ''],
                [isRTL ? 'هامش الأمان' : 'Margin of Safety',            res.margin_of_safety !== null ? formatCurrency(res.margin_of_safety) : '—', 'fw-semibold text-success'],
            ];
            document.getElementById('beBreakdown').innerHTML = rows.map(([label, val, cls]) =>
                `<tr class="${cls}"><td class="py-2">${label}</td><td class="text-end py-2">${val}</td></tr>`
            ).join('');
        }

        // ── KPI Dashboard ────────────────────────────────────────────────────────
        async function loadKpi() {
            const date = document.getElementById('kpiDate').value;
            const res  = await apiCall('{{ route('reports.kpi-dashboard') }}' + (date ? `?date=${date}` : ''));
            if (!res) return;

            const t = res.today || {};
            const m = res.month || {};
            const a = res.alerts || {};
            const isRTL = document.documentElement.dir === 'rtl';

            document.getElementById('kpiRevenue').textContent   = formatCurrency(t.revenue);
            document.getElementById('kpiInvoices').textContent  = t.invoice_count;
            document.getElementById('kpiAvg').textContent       = formatCurrency(t.avg_invoice);
            document.getElementById('kpiGross').textContent     = formatCurrency(t.gross_profit);
            document.getElementById('kpiGrossPct').textContent  = t.gross_margin_pct + '%';
            document.getElementById('kpiNet').textContent       = formatCurrency(t.net_profit);
            document.getElementById('kpiReturns').textContent   = formatCurrency(t.returns);
            document.getElementById('kpiMonthRevenue').textContent  = formatCurrency(m.revenue);
            document.getElementById('kpiMonthInvoices').textContent = m.invoice_count;
            document.getElementById('kpiMonthAvg').textContent      = formatCurrency(m.avg_invoice);
            document.getElementById('kpiLastUpdated').textContent   = new Date().toLocaleTimeString(isRTL ? 'ar-EG' : 'en-EG');

            let alertsHtml = '';
            if (a.below_margin_target) {
                alertsHtml += `<div class="alert alert-warning py-2"><i class="fas fa-triangle-exclamation me-1"></i>${isRTL ? `هامش الربح الإجمالي ${t.gross_margin_pct}% أقل من المستهدف ${a.margin_target_pct}%` : `Gross margin ${t.gross_margin_pct}% is below target ${a.margin_target_pct}%`}</div>`;
            }
            if (a.out_of_stock_count > 0) {
                alertsHtml += `<div class="alert alert-danger py-2"><i class="fas fa-box-open me-1"></i>${isRTL ? `${a.out_of_stock_count} منتجات نفد مخزونها` : `${a.out_of_stock_count} products out of stock`}</div>`;
            }
            if (a.low_stock_count > 0) {
                alertsHtml += `<div class="alert alert-warning py-2"><i class="fas fa-boxes-stacked me-1"></i>${isRTL ? `${a.low_stock_count} منتجات تقترب من نفاد المخزون` : `${a.low_stock_count} products low on stock`}</div>`;
            }
            document.getElementById('kpiAlerts').innerHTML = alertsHtml;
        }

        // ── Supplier Rating ──────────────────────────────────────────────────────
        async function loadSupplierRating() {
            const start = document.getElementById('srStart').value;
            const end   = document.getElementById('srEnd').value;
            if (!start || !end) return;
            const res = await apiCall('{{ route('reports.supplier-rating') }}', 'POST', { start_date: start, end_date: end });
            if (!res) return;

            const isRTL = document.documentElement.dir === 'rtl';
            const suppliers = res.suppliers || [];

            function onTimeBadge(pct) {
                if (pct === null || pct === undefined) return '<span class="text-muted small">—</span>';
                const cls = pct >= 90 ? 'bg-success' : pct >= 70 ? 'bg-warning' : 'bg-danger';
                return `<span class="badge ${cls}">${pct}%</span>`;
            }

            document.getElementById('srBody').innerHTML = suppliers.length
                ? suppliers.map(s => `<tr>
                    <td class="fw-semibold">${escapeHtml(s.supplier_name)}</td>
                    <td class="text-center">${s.total_pos}</td>
                    <td class="text-center text-success">${s.received_count}</td>
                    <td class="text-center text-danger">${s.cancelled_count}</td>
                    <td class="text-center">${onTimeBadge(s.on_time_pct)}</td>
                    <td class="text-center">${s.avg_lead_days !== null ? s.avg_lead_days : '—'}</td>
                    <td class="text-end fw-semibold">${formatCurrency(s.total_value)}</td>
                  </tr>`).join('')
                : `<tr><td colspan="7" class="text-center text-muted py-4">${isRTL ? 'لا توجد بيانات' : 'No data'}</td></tr>`;
        }

        loadSalesReport();
    </script>
@endpush
