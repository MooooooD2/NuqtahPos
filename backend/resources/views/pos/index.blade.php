@extends('layouts.app')
@section('title', __('pos.pos'))
@section('page-title', __('pos.pos'))

@push('styles')
    <style @nonce>
        .pos-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 1rem;
            min-height: calc(100vh - 130px);
        }

        .pos-left {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            overflow: hidden;
        }

        .pos-right {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            overflow-y: auto;
        }

        .cart-table-wrapper {
            flex: 1;
            overflow-y: auto;
        }

        .product-search {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1030;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
            max-height: 320px;
            overflow-y: auto;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .search-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .search-item:hover {
            background: var(--primary-light, #E8F0FC);
        }

        .search-item .barcode-badge {
            font-family: monospace;
            font-size: 0.75rem;
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            color: #64748b;
        }

        #cartTable thead.sticky-top {
            z-index: 1;
        }

        .cart-row td {
            vertical-align: middle;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .total-section {
            background: var(--primary, #12244E);
            color: #fff;
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .payment-btn {
            flex: 1;
            padding: 0.6rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .tax-row {
            background: #fef9ee;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
        }

        [data-theme="dark"] .tax-row           { background: #1e2d1a !important; }
        [data-theme="dark"] .search-results    { color: var(--body-color); }
        [data-theme="dark"] .search-item .barcode-badge { background: #334155; color: #94a3b8; }
        [data-theme="dark"] .pos-right .card   { border: 1px solid var(--card-border); }
        [data-theme="dark"] #selectedCustomerDisplay { background: #1e293b !important; color: var(--body-color); }

        /* ── Cart table dark mode ────────────────────────────────────────── */
        /* table-light thead — Bootstrap hardcodes #f8f9fa bg */
        [data-theme="dark"] thead.table-light th,
        [data-theme="dark"] .table-light {
            --bs-table-bg:           #0f172a !important;
            --bs-table-color:        #e2e8f0 !important;
            --bs-table-border-color: #334155 !important;
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #334155 !important;
        }

        /* Cart tbody rows */
        [data-theme="dark"] #cartTable tbody tr {
            background-color: var(--card-bg) !important;
            color: var(--body-color) !important;
        }

        [data-theme="dark"] #cartTable tbody tr:hover {
            background-color: #273548 !important;
        }

        /* Cart qty/price inputs */
        [data-theme="dark"] #cartTable input.form-control {
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
            border-color: #475569 !important;
        }

        /* Payment buttons inactive state */
        [data-theme="dark"] .payment-btn.btn-outline-secondary {
            color: #94a3b8 !important;
            border-color: #475569 !important;
            background: transparent !important;
        }

        [data-theme="dark"] .payment-btn.btn-outline-secondary:hover {
            background: #334155 !important;
            color: #e2e8f0 !important;
        }

        /* Scanner pulse animation */
        @keyframes scanPulse {

            0%,
            100% {
                opacity: 1
            }

            50% {
                opacity: 0.3
            }
        }

        .scanning {
            animation: scanPulse 0.5s ease-in-out;
        }

        @media (max-width: 960px) {
            .pos-layout {
                grid-template-columns: 1fr;
            }

            .pos-right {
                order: -1;
            }
        }
    </style>
@endpush

@section('content')
    <div class="pos-layout">
        {{-- Left: Search & Cart --}}
        <div class="pos-left">

            {{-- Barcode Search --}}
            <div class="card">
                <div class="card-body py-2">
                    <div class="product-search">
                        <div class="input-group">
                            <span class="input-group-text" id="barcodeIcon" title="Scan or type">
                                <i class="fas fa-barcode"></i>
                            </span>
                            <input type="text" class="form-control form-control-lg" id="searchInput"
                                placeholder="{{ __('pos.scan_barcode') }} / {{ __('pos.search_product') }}"
                                autocomplete="off" autofocus>
                            <button class="btn btn-outline-secondary" id="cameraScanBtn" title="{{ app()->getLocale() === 'ar' ? 'مسح بالكاميرا' : 'Camera scan' }}">
                                <i class="fas fa-camera"></i>
                            </button>
                            <button class="btn btn-outline-success" id="posAddProductBtn"
                                title="{{ app()->getLocale() === 'ar' ? 'إضافة منتج جديد' : 'Add new product' }}">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-primary" id="searchTriggerBtn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div class="search-results" id="searchResults"></div>
                    </div>
                </div>
            </div>

            {{-- Cart --}}
            <div class="card flex-grow-1">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-shopping-cart me-2 text-primary"></i>
                        <span id="cartTitle">{{ __('pos.cart') }}</span>
                    </span>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-primary rounded-pill" id="cartCount">0</span>
                        <button class="btn btn-sm btn-outline-danger" id="clearCartBtn"
                            style="display:none">
                            <i class="fas fa-trash me-1"></i>{{ __('pos.cancel') }}
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="cartTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width:35px">#</th>
                                <th>{{ __('pos.product_name') }}</th>
                                <th style="width:100px">{{ __('pos.unit_price') }}</th>
                                <th style="width:120px">{{ __('pos.quantity') }}</th>
                                <th style="width:110px">{{ __('pos.subtotal') }}</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyRow">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-barcode fa-3x mb-3 d-block opacity-20"></i>
                                    {{ __('pos.scan_barcode') }}<br>
                                    <small class="text-muted">{{ __('pos.cart_empty') }}</small>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right: Order Summary --}}
        <div class="pos-right">

            {{-- Customer Search --}}
            <div class="card">
                <div class="card-body py-2">
                    <label class="form-label fw-semibold small mb-1">
                        <i class="fas fa-user me-1 text-primary"></i>
                        {{ app()->getLocale() === 'ar' ? 'العميل (اختياري)' : 'Customer (optional)' }}
                    </label>
                    {{-- Selected customer display --}}
                    <div id="selectedCustomerDisplay" class="align-items-center justify-content-between mb-1 p-2 rounded bg-light d-none">
                        <div>
                            <div class="fw-semibold small" id="selectedCustomerName"></div>
                            <div class="text-muted" style="font-size:0.78rem" id="selectedCustomerPhone"></div>
                        </div>
                        <button class="btn btn-sm btn-outline-danger py-0 px-1" id="clearCustomerBtn">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                    {{-- Search input --}}
                    <div id="customerSearchBox" class="position-relative">
                        <input type="text" class="form-control form-control-sm" id="customerSearchInput"
                            placeholder="{{ app()->getLocale() === 'ar' ? 'ابحث باسم أو هاتف...' : 'Search by name or phone...' }}"
                            autocomplete="off">
                        <div id="customerSearchResults" class="search-results" style="max-height:200px"></div>
                    </div>
                    {{-- Quick-add form (hidden by default) --}}
                    <div id="customerQuickAdd" class="mt-2 p-2 border rounded" style="display:none">
                        <div class="small fw-semibold mb-2 text-success">
                            <i class="fas fa-user-plus me-1"></i>
                            {{ app()->getLocale() === 'ar' ? 'إضافة عميل جديد' : 'Add new customer' }}
                        </div>
                        <input type="text" class="form-control form-control-sm mb-1" id="newCustomerName"
                            placeholder="{{ app()->getLocale() === 'ar' ? 'الاسم' : 'Name' }}">
                        <input type="tel" class="form-control form-control-sm mb-1" id="newCustomerPhone"
                            placeholder="{{ app()->getLocale() === 'ar' ? 'رقم الهاتف / واتساب' : 'Phone / WhatsApp' }}">
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-success flex-grow-1" id="saveNewCustomerBtn">
                                <i class="fas fa-plus me-1"></i>{{ app()->getLocale() === 'ar' ? 'حفظ' : 'Save' }}
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" id="cancelQuickAddBtn">
                                {{ app()->getLocale() === 'ar' ? 'إلغاء' : 'Cancel' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Totals --}}
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">{{ __('pos.subtotal') }}</span>
                        <span id="displaySubtotal">{{ $settings['currency_symbol'] ?? 'ج.م' }} 0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2 align-items-center">
                        <span class="text-muted">{{ __('pos.discount') }}</span>
                        <div class="input-group input-group-sm" style="width:140px;">
                            <input type="number" class="form-control text-end" id="discountInput" value="0"
                                min="0" step="0.01">
                            <span class="input-group-text">{{ $settings['currency_symbol'] ?? 'ج.م' }}</span>
                        </div>
                    </div>

                    @if ($settings['tax_enabled'])
                        <div class="d-flex justify-content-between mb-2 align-items-center tax-row" id="taxRow">
                            <span class="text-warning fw-semibold">
                                <i class="fas fa-percent me-1"></i>
                                {{ app()->getLocale() === 'ar' ? $settings['tax_name_ar'] : $settings['tax_name_en'] }}
                                ({{ $settings['tax_rate'] }}%)
                                @if ($settings['tax_inclusive'])
                                    <small class="text-muted"> - {{ __('pos.tax_inclusive') }}</small>
                                @endif
                            </span>
                            <span id="displayTax" class="text-warning fw-semibold">0.00</span>
                        </div>
                    @endif

                    <hr class="my-2">
                    <div class="total-section">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-5">{{ __('pos.total') }}</span>
                            <span class="fs-2 fw-bold" id="displayTotal">0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Payment Method --}}
            <div class="card">
                <div class="card-body pb-2">
                    <label class="form-label fw-semibold mb-2">{{ __('pos.payment_method') }}</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="payment-btn btn" id="btnCash">
                            <i class="fas fa-money-bill-wave d-block mb-1"></i>{{ __('pos.cash') }}
                        </button>
                        <button class="payment-btn btn btn-outline-secondary" id="btnCard">
                            <i class="fas fa-credit-card d-block mb-1"></i>{{ __('pos.card') }}
                        </button>
                        <button class="payment-btn btn btn-outline-secondary" id="btnTransfer">
                            <i class="fas fa-right-left d-block mb-1"></i>{{ __('pos.transfer') }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Cash Received --}}
            <div class="card" id="cashPanel">
                <div class="card-body py-2">
                    <label class="form-label fw-semibold small">{{ __('pos.cash') }} {{ __('pos.amount') }}</label>
                    <input type="number" class="form-control" id="cashReceived" placeholder="0.00">
                    <div class="d-flex justify-content-between mt-2">
                        <span class="text-muted small">{{ app()->getLocale() === 'ar' ? 'الباقي' : 'Change' }}</span>
                        <span class="fw-bold text-success" id="changeAmount">0.00</span>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div class="card" id="notesPanel">
                <div class="card-body py-2">
                    <label class="form-label fw-semibold small">{{ app()->getLocale() === 'ar' ? 'ملاحظات الفاتورة' : 'Invoice Notes' }}</label>
                    <textarea class="form-control form-control-sm" id="invoiceNotes" rows="2" maxlength="500" placeholder="{{ app()->getLocale() === 'ar' ? 'أي ملاحظة خاصة...' : 'Any special notes...' }}"></textarea>
                </div>
            </div>

            {{-- Status indicator & auto-save badge --}}
            <div class="d-flex justify-content-between align-items-center px-1">
                <span id="autoSaveStatus" class="text-muted small" style="font-size:11px;"></span>
                <span id="systemStatusBadge" class="badge bg-success" style="font-size:10px;">
                    <i class="fas fa-circle me-1" style="font-size:8px;"></i>{{ app()->getLocale() === 'ar' ? 'متصل' : 'Online' }}
                </span>
            </div>

            {{-- Offline queue banner --}}
            <div id="offlineQueueBanner" class="d-none rounded p-2 d-flex align-items-center gap-2" style="background:rgba(234,179,8,.15);border:1px solid rgba(234,179,8,.4);font-size:12px;">
                <i class="fas fa-clock-rotate-left text-warning"></i>
                <span class="flex-grow-1">
                    <strong id="offlineQueueCount">0</strong>
                    {{ app()->getLocale() === 'ar' ? 'فاتورة معلقة للمزامنة' : 'pending invoice(s) to sync' }}
                </span>
                <button class="btn btn-sm btn-warning py-0 px-2" onclick="syncOfflineQueue()" style="font-size:11px;">
                    {{ app()->getLocale() === 'ar' ? 'مزامنة' : 'Sync' }}
                </button>
            </div>

            {{-- Complete Sale --}}
            <button class="btn btn-success btn-lg py-3 fw-bold" id="completeSaleBtn" disabled>
                <i class="fas fa-circle-check me-2"></i>{{ __('pos.complete_sale') }}
            </button>
        </div>
    </div>

    {{-- Invoice Modal --}}
    <div class="modal fade" id="invoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-receipt me-2 text-success"></i>{{ __('pos.print_invoice') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="invoiceBody"></div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                    <button class="btn btn-outline-primary" id="printInvoiceBtn">
                        <i class="fas fa-print me-2"></i>{{ __('pos.print') }}
                    </button>
                    <button class="btn btn-outline-success d-none" id="waInvoiceBtn">
                        <i class="fab fa-whatsapp me-2"></i>{{ app()->getLocale() === 'ar' ? 'إرسال واتساب' : 'Send WhatsApp' }}
                    </button>
                    <button class="btn btn-success" id="newSaleBtn">
                        <i class="fas fa-plus me-2"></i>{{ app()->getLocale() === 'ar' ? 'بيعة جديدة' : 'New Sale' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── CAMERA BARCODE SCANNER MODAL (html5-qrcode — identical to warehouse) ── --}}
    <div class="modal fade" id="cameraScanModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-camera me-2"></i>
                        {{ app()->getLocale() === 'ar' ? 'مسح الباركود' : 'Scan Barcode' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    {{-- html5-qrcode renders the viewfinder + scan frame here --}}
                    <div id="posScannerReader" class="w-100"></div>

                    {{-- Manual / physical-scanner fallback --}}
                    <div class="p-3 border-top">
                        <p class="text-muted small mb-2">
                            <i class="fas fa-keyboard me-1"></i>
                            {{ app()->getLocale() === 'ar'
                                ? 'أو أدخل الباركود يدوياً (يدعم الماسح الضوئي الفيزيائي):'
                                : 'Or enter barcode manually (physical scanner supported):' }}
                        </p>
                        <div class="input-group">
                            <input type="text" class="form-control font-monospace" id="posManualBarcodeInput"
                                placeholder="{{ app()->getLocale() === 'ar' ? 'اكتب أو امسح واضغط Enter' : 'Type or scan & press Enter' }}"
                                autocomplete="off">
                            <button class="btn btn-primary" id="posManualSearchBtn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── QUICK-ADD PRODUCT MODAL (POS) ────────────────────────────────────── --}}
    @php $isAr = app()->getLocale() === 'ar'; @endphp
    <div class="modal fade" id="posAddProductModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="posAddProductTitle">
                        <i class="fas fa-plus-circle me-2"></i>
                        {{ $isAr ? 'إضافة منتج جديد' : 'Add New Product' }}
                    </h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    {{-- Barcode not-found notice (shown only when triggered from failed scan) --}}
                    <div id="posBarcodeNotFoundAlert" class="alert alert-warning d-flex align-items-center gap-2 py-2 d-none mb-3">
                        <i class="fas fa-barcode flex-shrink-0"></i>
                        <div>
                            <strong>{{ $isAr ? 'الباركود غير موجود' : 'Barcode not found' }}</strong>
                            <div class="small">{{ $isAr ? 'يمكنك إضافة المنتج الآن وسيُضاف للفاتورة تلقائياً.' : 'Add the product now and it will be added to the cart automatically.' }}</div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                {{ __('pos.product_name') }} <span class="text-danger">*</span>
                                <span id="posNameLookupBadge" class="d-none ms-2 small text-muted">
                                    <span class="spinner-border spinner-border-sm align-middle me-1" style="width:.75rem;height:.75rem;"></span>
                                    {{ $isAr ? 'جاري البحث...' : 'Looking up…' }}
                                </span>
                                <span id="posNameFoundBadge" class="d-none ms-2 small text-success">
                                    <i class="fas fa-check-circle me-1"></i>{{ $isAr ? 'تم جلب الاسم' : 'Name fetched' }}
                                </span>
                            </label>
                            <input type="text" class="form-control" id="posNewName"
                                placeholder="{{ $isAr ? 'اسم المنتج' : 'Product name' }}" autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('pos.selling_price') }} <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="posNewPrice" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('pos.cost_price') }}</label>
                            <input type="number" class="form-control" id="posNewCostPrice" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('pos.barcode') }}</label>
                            <input type="text" class="form-control font-monospace" id="posNewBarcode"
                                placeholder="{{ $isAr ? 'باركود (اختياري)' : 'Barcode (optional)' }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('pos.category') }}</label>
                            <input type="text" class="form-control" id="posNewCategory" list="posCategoryList"
                                placeholder="{{ $isAr ? 'الفئة (اختياري)' : 'Category (optional)' }}">
                            <datalist id="posCategoryList"></datalist>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ $isAr ? 'كمية البداية' : 'Initial Stock' }}</label>
                            <input type="number" class="form-control" id="posNewQuantity" min="0" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">{{ __('pos.min_stock') }}</label>
                            <input type="number" class="form-control" id="posNewMinStock" min="0" value="5">
                        </div>
                    </div>

                    {{-- Add to cart checkbox --}}
                    <div class="form-check mt-3 p-3 bg-success bg-opacity-10 rounded">
                        <input class="form-check-input" type="checkbox" id="posAddToCartAfterSave" checked>
                        <label class="form-check-label fw-semibold text-success" for="posAddToCartAfterSave">
                            <i class="fas fa-cart-plus me-1"></i>
                            {{ $isAr ? 'أضف للفاتورة الحالية بعد الحفظ' : 'Add to current cart after saving' }}
                        </label>
                    </div>

                    {{-- Saving error --}}
                    <div id="posAddProductError" class="alert alert-danger mt-3 d-none py-2"></div>

                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ $isAr ? 'إلغاء' : 'Cancel' }}
                    </button>
                    <button class="btn btn-success" id="posSaveNewProductBtn">
                        <i class="fas fa-floppy-disk me-1"></i>
                        {{ $isAr ? 'حفظ المنتج' : 'Save Product' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/build/qrcode.min.js"></script>
    <script src="{{ asset('js/pos-offline.js') }}?v={{ filemtime(public_path('js/pos-offline.js')) }}"></script>
    <script @nonce>
        // Settings passed from controller
        const POS_SETTINGS = {
            waEnabled: {{ $waEnabled ? 'true' : 'false' }},
            taxEnabled: {{ $settings['tax_enabled'] ? 'true' : 'false' }},
            taxRate: {{ (float) ($settings['tax_rate'] ?? 0) }},
            taxInclusive: {{ $settings['tax_inclusive'] ? 'true' : 'false' }},
            taxNameAr: '{{ addslashes($settings['tax_name_ar'] ?? 'ضريبة') }}',
            taxNameEn: '{{ addslashes($settings['tax_name_en'] ?? 'VAT') }}',
            posSound: {{ $settings['pos_sound'] ? 'true' : 'false' }},
            invoiceFooter: '{{ addslashes($settings['invoice_footer'] ?? '') }}',
            storeName: '{{ addslashes($settings['store_name'] ?? '') }}',
            storeAddress: '{{ addslashes($settings['store_address'] ?? '') }}',
            storePhone: '{{ addslashes($settings['store_phone'] ?? '') }}',
            defaultPayment: '{{ $settings['default_payment'] ?? 'cash' }}',
            autoPrint: {{ $settings['auto_print'] ? 'true' : 'false' }},
            currencySymbol: '{{ $settings['currency_symbol'] ?? 'ج.م' }}',
            allowPriceChange: {{ \App\Models\Setting::get('allow_cashier_price_change', true) ? 'true' : 'false' }},
        };

        let cart = [];
        let paymentMethod = POS_SETTINGS.defaultPayment;
        let searchTimeout = null;
        let searchAbort = null;   // AbortController for in-flight search requests
        let lastKeyTime = Date.now();
        let currentInvoice = null;
        let lastSearchResults = [];

        // ── Customer widget state ──────────────────────────────────────────────────
        let selectedCustomerId = null;
        let customerSearchTimeout = null;
        let _customerResults = [];

        // ─── BARCODE SCANNER SUPPORT ──────────────────────────────────────────────────
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            const now = Date.now();
            const timeDiff = now - lastKeyTime;
            lastKeyTime = now;

            if (e.key === 'Enter') {
                e.preventDefault();
                const val = this.value.trim();
                if (val) {
                    const isScanner = timeDiff < 80;
                    handleSearch(val, isScanner);
                }
                return;
            }
        });

        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const q = this.value.trim();
            if (q.length < 2) {
                closeSearch();
                return;
            }
            searchTimeout = setTimeout(() => showSearchResults(q), 150);
        });

        function triggerSearch() {
            const val = document.getElementById('searchInput').value.trim();
            if (val) handleSearch(val, false);
        }

        async function handleSearch(query, isScanner) {
            closeSearch();
            if (searchAbort) searchAbort.abort();
            searchAbort = new AbortController();

            // Offline: search local IndexedDB cache
            if (!navigator.onLine) {
                const product = await PosDB.findExact(query);
                if (product) {
                    addToCart(product);
                    document.getElementById('searchInput').value = '';
                    if (POS_SETTINGS.posSound) beep();
                } else if (isScanner) {
                    openAddProductModal(query, true);
                } else {
                    showToast('{{ __('pos.product_not_found') }}', 'danger');
                    document.getElementById('searchInput').value = '';
                }
                return;
            }

            try {
                const url = `{{ route('products.search') }}?query=${encodeURIComponent(query)}&exact=${isScanner ? 1 : 0}`;
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    signal: searchAbort.signal,
                });
                const res = await response.json();
                if (!res.success) {
                    // Barcode scan: offer to create the product instead of just a toast
                    if (isScanner) {
                        openAddProductModal(query, true);   // use captured 'query', not stale DOM value
                    } else {
                        showToast(res.message || '{{ __('pos.product_not_found') }}', 'danger');
                        document.getElementById('searchInput').value = '';
                    }
                    return;
                }
                if (res.single) {
                    PosDB.cacheProducts([res.product]).catch(() => {});
                    addToCart(res.product);
                    document.getElementById('searchInput').value = '';
                    if (POS_SETTINGS.posSound) beep();
                } else {
                    PosDB.cacheProducts(res.products).catch(() => {});
                    renderSearchDropdown(res.products);
                }
            } catch (e) {
                if (e.name === 'AbortError') return;
                // Network error — try offline cache
                const product = await PosDB.findExact(query).catch(() => null);
                if (product) {
                    addToCart(product);
                    document.getElementById('searchInput').value = '';
                    if (POS_SETTINGS.posSound) beep();
                } else if (isScanner) {
                    openAddProductModal(query, true);
                } else {
                    showToast('{{ __('pos.product_not_found') }}', 'danger');
                }
            }
        }

        async function showSearchResults(query) {
            if (searchAbort) searchAbort.abort();
            searchAbort = new AbortController();

            // Offline: search local IndexedDB cache
            if (!navigator.onLine) {
                const results = await PosDB.searchProducts(query).catch(() => []);
                if (results.length === 1) {
                    addToCart(results[0]);
                    document.getElementById('searchInput').value = '';
                    closeSearch();
                    if (POS_SETTINGS.posSound) beep();
                } else if (results.length > 1) {
                    renderSearchDropdown(results);
                } else {
                    closeSearch();
                }
                return;
            }

            try {
                const response = await fetch(`{{ route('products.search') }}?query=${encodeURIComponent(query)}&exact=0`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                    signal: searchAbort.signal,
                });
                const res = await response.json();
                if (!res.success) { closeSearch(); return; }
                if (res.single) {
                    PosDB.cacheProducts([res.product]).catch(() => {});
                    addToCart(res.product);
                    document.getElementById('searchInput').value = '';
                    closeSearch();
                    if (POS_SETTINGS.posSound) beep();
                } else if (res.products?.length) {
                    PosDB.cacheProducts(res.products).catch(() => {});
                    renderSearchDropdown(res.products);
                }
            } catch (e) {
                if (e.name === 'AbortError') return;
                // Network error — fall back to IndexedDB
                const results = await PosDB.searchProducts(query).catch(() => []);
                if (results.length) renderSearchDropdown(results);
                else closeSearch();
            }
        }

        function renderSearchDropdown(products) {
            lastSearchResults = products;
            const container = document.getElementById('searchResults');
            const isAr = LOCALE === 'ar';
            let html = products.map((p, i) => `
        <div class="search-item" data-product-idx="${i}">
            <div>
                <div class="fw-semibold">${escapeHtml(p.name)}</div>
                <small class="text-muted">${p.category || ''}</small>
            </div>
            <div class="text-end">
                <div class="fw-bold text-success">${formatCurrency(p.price)}</div>
                ${p.barcode ? `<span class="barcode-badge">${escapeHtml(p.barcode)}</span>` : ''}
                <small class="text-${p.quantity > 0 ? 'success' : 'danger'} d-block">${p.quantity} ${p.unit_abbreviation || p.unit_name || (isAr ? 'قطعة' : 'pcs')}</small>
            </div>
        </div>`).join('');
            // "Add new product" footer
            html += `<div class="search-item text-success border-top" style="font-size:.85rem" id="posAddFromSearchOption">
                <i class="fas fa-plus-circle me-1"></i>
                ${isAr ? 'إضافة منتج جديد' : 'Add new product'}
            </div>`;
            container.innerHTML = html;
            container.classList.add('show');
        }

        function selectProduct(product) {
            addToCart(product);
            document.getElementById('searchInput').value = '';
            closeSearch();
            if (POS_SETTINGS.posSound) beep();
        }

        function closeSearch() {
            document.getElementById('searchResults').classList.remove('show');
        }

        // ─── CART MANAGEMENT ─────────────────────────────────────────────────────────
        function addToCart(product) {
            const existing = cart.find(i => i.product_id === product.id);
            if (existing) {
                if (existing.quantity + 1 > existing.max_qty) {
                    showToast('{{ __('pos.insufficient_stock') }}', 'danger');
                    return;
                }
                existing.quantity++;
                // تنبيه لو الكمية المتبقية بعد البيع أقل من min_stock
                const remaining = existing.max_qty - existing.quantity;
                if (remaining <= (product.min_stock || 5) && remaining > 0) {
                    showToast(`⚠️ تنبيه: ${product.name} — متبقي ${remaining} قطعة فقط`, 'warning');
                }
            } else {
                if (product.quantity <= 0) {
                    showToast('{{ __('pos.insufficient_stock') }}', 'danger');
                    return;
                }
                cart.push({
                    product_id:       product.id,
                    product_name:     product.name,
                    price:            product.price,
                    quantity:         1,
                    max_qty:          product.quantity,
                    min_stock:        product.min_stock || 5,
                    unit_abbreviation: product.unit_abbreviation || product.unit_name || null,
                });
                // تنبيه لو المنتج أصلاً على وشك النفاذ
                if (product.quantity <= (product.min_stock || 5)) {
                    showToast(`⚠️ ${product.name} — مخزون منخفض (${product.quantity} قطعة)`, 'warning');
                }
            }
            renderCart();
        }

        function buildCartRowHTML(item, idx) {
            return `<tr class="cart-row" data-cart-idx="${idx}" data-product-id="${item.product_id}">
            <td class="text-muted small">${idx + 1}</td>
            <td><div class="fw-semibold">${escapeHtml(item.product_name)}${item.unit_abbreviation ? ` <span class="badge bg-secondary ms-1" style="font-size:.7rem">${escapeHtml(item.unit_abbreviation)}</span>` : ''}</div></td>
            <td class="text-end fw-semibold text-success" data-cell="price">${formatCurrency(item.price)}</td>
            <td class="text-center">
                <div class="d-flex align-items-center gap-1">
                    <button class="btn btn-sm btn-outline-secondary qty-btn" data-action="dec-qty" data-idx="${idx}">−</button>
                    <input type="number" class="form-control form-control-sm text-center p-1"
                        style="width:55px" value="${item.quantity}" min="1" max="${item.max_qty}"
                        data-action="set-qty" data-idx="${idx}">
                    <button class="btn btn-sm btn-outline-secondary qty-btn" data-action="inc-qty" data-idx="${idx}">+</button>
                </div>
            </td>
            <td class="fw-semibold text-success text-end" data-cell="total">${formatCurrency(item.price * item.quantity)}</td>
            <td class="text-center">
                <button class="btn btn-sm btn-outline-danger qty-btn" data-action="remove" data-idx="${idx}">
                    <i class="fas fa-xmark"></i>
                </button>
            </td>
        </tr>`;
        }

        function renderCart() {
            const tbody = document.getElementById('cartBody');

            if (cart.length === 0) {
                tbody.innerHTML = `<tr id="emptyRow"><td colspan="6" class="text-center text-muted py-5">
            <i class="fas fa-barcode fa-3x mb-3 d-block opacity-20"></i>
            {{ __('pos.scan_barcode') }}<br><small>{{ __('pos.cart_empty') }}</small>
         </td></tr>`;
                document.getElementById('completeSaleBtn').disabled = true;
                document.getElementById('clearCartBtn').style.display = 'none';
                document.getElementById('cartCount').textContent = 0;
                updateTotals();
                return;
            }

            // Remove empty-state row if present (it has no .cart-row class so querySelector misses it)
            const emptyRow = document.getElementById('emptyRow');
            if (emptyRow) emptyRow.remove();

            const existingRows = tbody.querySelectorAll('tr.cart-row');

            // Patch existing rows / add new ones / remove extras
            cart.forEach((item, idx) => {
                const existing = existingRows[idx];
                const newHTML = buildCartRowHTML(item, idx);
                if (!existing) {
                    tbody.insertAdjacentHTML('beforeend', newHTML);
                } else {
                    // Only replace the row if data-cart-idx changed (item reordered/removed)
                    // or update just the dynamic cells to avoid losing input focus
                    const idxAttr = parseInt(existing.dataset.cartIdx);
                    if (idxAttr !== idx || existing.dataset.productId !== String(item.product_id)) {
                        existing.outerHTML = newHTML;
                    } else {
                        // Update quantity input, price display, and total cell
                        const qtyInput  = existing.querySelector('[data-action="set-qty"]');
                        const priceCell = existing.querySelector('[data-cell="price"]');
                        const totalCell = existing.querySelector('[data-cell="total"]');
                        if (document.activeElement !== qtyInput) qtyInput.value = item.quantity;
                        if (priceCell) priceCell.textContent = formatCurrency(item.price);
                        totalCell.textContent = formatCurrency(item.price * item.quantity);
                    }
                }
            });

            // Remove surplus rows (items were deleted)
            for (let i = cart.length; i < existingRows.length; i++) {
                existingRows[i].remove();
            }

            document.getElementById('completeSaleBtn').disabled = false;
            document.getElementById('clearCartBtn').style.display = 'inline-block';
            document.getElementById('cartCount').textContent = cart.reduce((s, i) => s + i.quantity, 0);
            updateTotals();
        }

        function changeQty(idx, delta) {
            const newQty = cart[idx].quantity + delta;
            if (newQty >= 1 && newQty <= cart[idx].max_qty) {
                cart[idx].quantity = newQty;
                renderCart();
            }
        }

        function setQty(idx, val) {
            let newQty = parseInt(val) || 1;
            newQty = Math.max(1, Math.min(cart[idx].max_qty, newQty));
            cart[idx].quantity = newQty;
            renderCart();
        }

        function setPrice(idx, val) {
            cart[idx].price = Math.max(0, parseFloat(val) || 0);
            updateTotals();
        }

        function removeItem(idx) {
            cart.splice(idx, 1);
            renderCart();
        }

        async function clearCart() {
            const isAr = LOCALE === 'ar';
            const confirmed = await confirmAction({
                title: isAr ? 'إلغاء الفاتورة؟' : 'Clear cart?',
                text: isAr ? 'سيتم حذف جميع المنتجات من الفاتورة الحالية.' : 'All items in the current sale will be removed.',
                confirmText: isAr ? 'نعم، امسح' : 'Yes, clear',
                confirmColor: '#ef4444',
                icon: 'warning',
            });
            if (!confirmed) return;
            cart = [];
            autoSaveDraftId = null;
            clearTimeout(autoSaveTimer);
            const notesEl = document.getElementById('invoiceNotes');
            if (notesEl) notesEl.value = '';
            document.getElementById('autoSaveStatus').textContent = '';
            renderCart();
        }

        // ─── TOTALS WITH TAX ─────────────────────────────────────────────────────────
        function updateTotals() {
            const subtotal = cart.reduce((s, i) => s + i.price * i.quantity, 0);
            const discount = parseFloat(document.getElementById('discountInput').value) || 0;
            const afterDiscount = subtotal - discount;

            let taxAmount = 0;
            let finalTotal = afterDiscount;

            if (POS_SETTINGS.taxEnabled && POS_SETTINGS.taxRate > 0) {
                if (POS_SETTINGS.taxInclusive) {
                    taxAmount = afterDiscount - (afterDiscount / (1 + POS_SETTINGS.taxRate / 100));
                } else {
                    taxAmount = afterDiscount * (POS_SETTINGS.taxRate / 100);
                    finalTotal = afterDiscount + taxAmount;
                }
            }

            document.getElementById('displaySubtotal').textContent = formatCurrency(subtotal);
            const taxEl = document.getElementById('displayTax');
            if (taxEl) taxEl.textContent = formatCurrency(taxAmount);
            document.getElementById('displayTotal').textContent = formatCurrency(finalTotal);
            calcChange();
        }

        function calcChange() {
            const totalText = document.getElementById('displayTotal').textContent.replace(/[^\d.-]/g, '');
            const total = parseFloat(totalText) || 0;
            const cash = parseFloat(document.getElementById('cashReceived').value) || 0;
            document.getElementById('changeAmount').textContent = formatCurrency(Math.max(0, cash - total));
        }

        // ─── PAYMENT ──────────────────────────────────────────────────────────────────
        function setPayment(method) {
            paymentMethod = method;
            ['btnCash', 'btnCard', 'btnTransfer'].forEach(btnId => {
                const btn = document.getElementById(btnId);
                const btnMethod = btnId.replace('btn', '').toLowerCase();
                if (btnMethod === method) {
                    btn.className = 'payment-btn btn btn-success';
                } else {
                    btn.className = 'payment-btn btn btn-outline-secondary';
                }
            });
            document.getElementById('cashPanel').style.display = method === 'cash' ? 'block' : 'none';
        }

        // ─── CUSTOMER WIDGET ─────────────────────────────────────────────────────────
        document.getElementById('customerSearchInput').addEventListener('input', function() {
            clearTimeout(customerSearchTimeout);
            const q = this.value.trim();
            if (q.length < 1) { closeCustomerSearch(); return; }
            customerSearchTimeout = setTimeout(() => searchCustomers(q), 250);
        });

        async function searchCustomers(q) {
            try {
                const res = await fetch(`{{ route('customers.search') }}?q=${encodeURIComponent(q)}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                });
                const data = await res.json();
                if (!data.success) return;
                renderCustomerDropdown(data.customers, q);
            } catch (e) {}
        }

        function renderCustomerDropdown(customers, q) {
            _customerResults = customers;
            const isAr = LOCALE === 'ar';
            const container = document.getElementById('customerSearchResults');
            let html = customers.map((c, i) => `
                <div class="search-item" data-cust-idx="${i}">
                    <div>
                        <div class="fw-semibold">${escapeHtml(c.name)}</div>
                        <small class="text-muted">${c.phone ? escapeHtml(c.phone) : ''}</small>
                    </div>
                    <span class="badge bg-primary">${escapeHtml(c.code)}</span>
                </div>`).join('');
            html += `<div class="search-item text-success border-top" id="addNewCustomerOption">
                <i class="fas fa-user-plus me-1"></i>
                ${isAr ? 'إضافة عميل جديد' : 'Add new customer'}
            </div>`;
            container.innerHTML = html;
            container.classList.add('show');
        }

        function closeCustomerSearch() {
            document.getElementById('customerSearchResults').classList.remove('show');
        }

        function selectCustomer(customer) {
            selectedCustomerId = customer.id;
            document.getElementById('selectedCustomerName').textContent = customer.name;
            document.getElementById('selectedCustomerPhone').textContent = customer.phone || '';
            document.getElementById('selectedCustomerDisplay').classList.remove('d-none');
            document.getElementById('selectedCustomerDisplay').classList.add('d-flex');
            document.getElementById('customerSearchBox').classList.add('d-none');
            closeCustomerSearch();
            document.getElementById('customerSearchInput').value = '';
        }

        function clearCustomer() {
            selectedCustomerId = null;
            document.getElementById('selectedCustomerDisplay').classList.add('d-none');
            document.getElementById('selectedCustomerDisplay').classList.remove('d-flex');
            document.getElementById('customerSearchBox').classList.remove('d-none');
            document.getElementById('customerQuickAdd').style.display = 'none';
            document.getElementById('newCustomerName').value = '';
            document.getElementById('newCustomerPhone').value = '';
        }

        document.getElementById('customerSearchResults').addEventListener('click', function(e) {
            const item = e.target.closest('[data-cust-idx]');
            if (item) { selectCustomer(_customerResults[parseInt(item.dataset.custIdx)]); return; }
            if (e.target.closest('#addNewCustomerOption')) {
                closeCustomerSearch();
                document.getElementById('customerQuickAdd').style.display = 'block';
                document.getElementById('newCustomerName').focus();
            }
        });

        document.getElementById('clearCustomerBtn').addEventListener('click', clearCustomer);
        document.getElementById('cancelQuickAddBtn').addEventListener('click', function() {
            document.getElementById('customerQuickAdd').style.display = 'none';
        });

        document.getElementById('saveNewCustomerBtn').addEventListener('click', async function() {
            const name = document.getElementById('newCustomerName').value.trim();
            const phone = document.getElementById('newCustomerPhone').value.trim();
            if (!name) { showToast(LOCALE === 'ar' ? 'الاسم مطلوب' : 'Name is required', 'danger'); return; }
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            try {
                const res = await apiCall('{{ route('customers.store') }}', 'POST', { name, phone });
                if (res.success) {
                    selectCustomer(res.customer);
                    document.getElementById('customerQuickAdd').style.display = 'none';
                    document.getElementById('newCustomerName').value = '';
                    document.getElementById('newCustomerPhone').value = '';
                    showToast(LOCALE === 'ar' ? 'تم إضافة العميل' : 'Customer added');
                } else {
                    showToast(res.message || (LOCALE === 'ar' ? 'خطأ' : 'Error'), 'danger');
                }
            } catch (e) {
                showToast(LOCALE === 'ar' ? 'خطأ في الاتصال' : 'Connection error', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-plus me-1"></i>${LOCALE === 'ar' ? 'حفظ' : 'Save'}`;
            }
        });

        // Close customer dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#customerSearchBox') && !e.target.closest('#customerSearchResults')) {
                closeCustomerSearch();
            }
        });

        // ─── COMPLETE SALE ────────────────────────────────────────────────────────────
        async function completeSale() {
            if (!cart.length) return;
            const btn = document.getElementById('completeSaleBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('pos.loading') }}';

            const discount     = parseFloat(document.getElementById('discountInput').value) || 0;
            const cashReceived = paymentMethod === 'cash'
                ? (parseFloat(document.getElementById('cashReceived').value) || 0)
                : null;
            const notes = (document.getElementById('invoiceNotes')?.value || '').trim();

            const invoicePayload = {
                items: cart.map(i => ({
                    product_id:   i.product_id,
                    product_name: i.product_name,
                    quantity:     i.quantity,
                    price:        i.price,
                })),
                discount,
                payment_method: paymentMethod,
                cash_received:  cashReceived,
                customer_id:    selectedCustomerId || null,
                notes:          notes || null,
            };

            // If offline — queue immediately without a network attempt
            if (!navigator.onLine) {
                await PosDB.queueInvoice({ payload: invoicePayload });
                await updateOfflineQueueBadge();
                const isRTL = document.documentElement.dir === 'rtl';
                showToast(isRTL ? 'تم حفظ الفاتورة — ستُرسل عند الاتصال' : 'Invoice saved — will sync when online', 'warning');
                newSale();
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-circle-check me-2"></i>{{ __('pos.complete_sale') }}';
                return;
            }

            try {
                const res = await apiCall('{{ route('invoices.create') }}', 'POST', invoicePayload);

                if (res.success) {
                    currentInvoice = res.invoice;
                    showInvoiceModal(res.invoice);
                    showToast('{{ __('pos.sale_completed') }}');
                    if (POS_SETTINGS.autoPrint) setTimeout(() => printInvoice(), 800);
                } else {
                    showToast(res.message, 'danger');
                }
            } catch (e) {
                // Network error mid-request — queue offline
                if (e instanceof TypeError || !navigator.onLine) {
                    await PosDB.queueInvoice({ payload: invoicePayload });
                    await updateOfflineQueueBadge();
                    const isRTL = document.documentElement.dir === 'rtl';
                    showToast(isRTL ? 'انقطع الاتصال — تم حفظ الفاتورة للمزامنة' : 'Connection lost — invoice queued for sync', 'warning');
                    newSale();
                } else {
                    console.error(e);
                    showToast('{{ __('pos.error') }}', 'danger');
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-circle-check me-2"></i>{{ __('pos.complete_sale') }}';
            }
        }

        // ─── INVOICE MODAL ────────────────────────────────────────────────────────────
        function showInvoiceModal(invoice) {
            const isRTL  = document.documentElement.dir === 'rtl' || document.documentElement.lang === 'ar';
            const alignment = isRTL ? 'right' : 'left';
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const C = {
                theadBg:      isDark ? '#0f172a'  : '#f8f9fa',
                theadColor:   isDark ? '#e2e8f0'  : '#212529',
                border:       isDark ? '#334155'  : '#dee2e6',
                mutedColor:   isDark ? '#94a3b8'  : '#6c757d',
                sectionBg:    isDark ? '#0B1830'  : '#f8f9fa',
                sectionColor: isDark ? '#e2e8f0'  : '#212529',
                taxBg:        isDark ? '#1e2d1a'  : '#fef9ee',
                taxColor:     isDark ? '#6ee7b7'  : '#856404',
                changeBg:     isDark ? '#451a03'  : '#fff3cd',
                changeColor:  isDark ? '#fcd34d'  : '#856404',
            };

            const showLineTax = POS_SETTINGS.taxEnabled && invoice.items.some(i => i.tax_rate > 0);
            const itemsHtml = invoice.items.map(i => `
        <tr>
            <td style="padding: 8px; text-align: ${alignment};">${escapeHtml(i.product_name)}</td>
            <td style="padding: 8px; text-align: center;">${i.quantity}${i.unit_abbreviation ? ` <small style="color:#6c757d">${escapeHtml(i.unit_abbreviation)}</small>` : ''}</td>
            <td style="padding: 8px; text-align: right;">${formatCurrency(i.price)}</td>
            ${showLineTax ? `<td style="padding:8px;text-align:right;font-size:11px;color:#6c757d;">${i.tax_rate > 0 ? i.tax_rate + '%' : '—'}</td>` : ''}
            <td style="padding: 8px; text-align: right;">${formatCurrency(i.subtotal)}</td>
        </tr>`).join('');

            const taxAmount = invoice.tax_amount || 0;
            const taxRate = invoice.tax_rate || POS_SETTINGS.taxRate;

            const taxRow = (POS_SETTINGS.taxEnabled && taxAmount > 0) ?
                `<tr style="background-color:${C.taxBg};">
            <td colspan="3" style="padding:8px;text-align:right;color:${C.taxColor};">
                ${getTaxName()} (${taxRate}%)
            </td>
            <td style="padding:8px;text-align:right;color:${C.taxColor};">+${formatCurrency(taxAmount)}</td>
        </tr>` :
                '';

            const discRow = (invoice.discount && invoice.discount > 0) ?
                `<tr>
            <td colspan="3" style="padding: 8px; text-align: right; color: #dc3545;">{{ __('pos.discount') }}</td>
            <td style="padding: 8px; text-align: right; color: #dc3545;">-${formatCurrency(invoice.discount)}</td>
        </tr>` :
                '';

            const invoiceHtml = `
        <div style="text-align: center; margin-bottom: 15px;">
            <h4 style="margin: 0; font-weight: bold;">${escapeHtml(POS_SETTINGS.storeName || '{{ __('pos.app_name') }}')}</h4>
            ${POS_SETTINGS.storeAddress ? `<p style="margin: 5px 0; color: #6c757d; font-size: 12px;">${escapeHtml(POS_SETTINGS.storeAddress)}</p>` : ''}
            ${POS_SETTINGS.storePhone ? `<p style="margin: 5px 0; color: #6c757d; font-size: 12px;"><i class="fas fa-phone"></i> ${escapeHtml(POS_SETTINGS.storePhone)}</p>` : ''}
            <hr style="margin: 10px 0;">
            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                <span style="color: #6c757d;">{{ __('pos.invoice_number') }}:</span>
                <span style="font-weight: bold;">${invoice.invoice_number}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 13px;">
                <span style="color: #6c757d;">{{ __('pos.date') }}:</span>
                <span>${new Date().toLocaleString()}</span>
            </div>
        </div>
        <table style="width:100%;border-collapse:collapse;margin-bottom:15px;color:${C.sectionColor};">
            <thead style="background-color:${C.theadBg};color:${C.theadColor};">
                <tr>
                    <th style="padding:8px;text-align:${alignment};border-bottom:2px solid ${C.border};color:${C.theadColor};">{{ __('pos.product_name') }}</th>
                    <th style="padding:8px;text-align:center;border-bottom:2px solid ${C.border};color:${C.theadColor};">{{ __('pos.quantity') }}</th>
                    <th style="padding:8px;text-align:right;border-bottom:2px solid ${C.border};color:${C.theadColor};">{{ __('pos.unit_price') }}</th>
                    ${showLineTax ? `<th style="padding:8px;text-align:right;border-bottom:2px solid ${C.border};color:${C.theadColor};font-size:11px;">{{ app()->getLocale() === 'ar' ? 'ض.ق.م' : 'VAT%' }}</th>` : ''}
                    <th style="padding:8px;text-align:right;border-bottom:2px solid ${C.border};color:${C.theadColor};">{{ __('pos.subtotal') }}</th>
                </tr>
            </thead>
            <tbody>${itemsHtml}</tbody>
            <tfoot>
                <tr style="border-top:1px solid ${C.border};color:${C.sectionColor};">
                    <td colspan="3" style="padding:8px;text-align:right;">{{ __('pos.subtotal') }}</td>
                    <td style="padding:8px;text-align:right;">${formatCurrency(invoice.subtotal || invoice.total)}</td>
                </tr>
                ${discRow}
                ${taxRow}
                <tr style="background-color:#12244E;color:#fff;font-weight:bold;">
                    <td colspan="3" style="padding:10px;text-align:right;">{{ __('pos.total') }}</td>
                    <td style="padding:10px;text-align:right;">${formatCurrency(invoice.final_total)}</td>
                </tr>
            </tfoot>
        </table>
        <div style="margin-top:15px;padding:12px;background:${C.sectionBg};border-radius:8px;font-size:13px;color:${C.sectionColor};">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="color:${C.mutedColor};">{{ app()->getLocale() === 'ar' ? 'طريقة الدفع' : 'Payment Method' }}</span>
                <span style="font-weight:600;color:${C.sectionColor};">${getPaymentMethodText(invoice.payment_method)}</span>
            </div>
            ${invoice.payment_method === 'cash' && invoice.cash_received != null ? `
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;color:#198754;">
                <span style="font-weight:600;">{{ app()->getLocale() === 'ar' ? '💵 المبلغ المدفوع' : '💵 Cash Received' }}</span>
                <span style="font-weight:700;font-size:15px;">${formatCurrency(invoice.cash_received)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px;background:${C.changeBg};border-radius:6px;font-weight:700;">
                <span style="color:${C.changeColor};">{{ app()->getLocale() === 'ar' ? '🔄 الباقي للزبون' : '🔄 Change Due' }}</span>
                <span style="color:${C.changeColor};font-size:16px;">${formatCurrency(invoice.change_amount ?? 0)}</span>
            </div>
            ` : ''}
        </div>
        ${POS_SETTINGS.invoiceFooter ? `<div style="text-align: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6; color: #6c757d; font-size: 11px;">${escapeHtml(POS_SETTINGS.invoiceFooter)}</div>` : ''}
        <div style="text-align:center;margin-top:12px;padding-top:10px;border-top:1px solid #dee2e6;">
            <canvas id="invoiceQrCanvas" style="max-width:110px;max-height:110px;"></canvas>
            <div style="font-size:10px;color:#6c757d;margin-top:4px;">{{ app()->getLocale() === 'ar' ? 'امسح للتحقق' : 'Scan to verify' }}</div>
        </div>
        <div style="text-align: center; margin-top: 10px; font-size: 11px;">
            <small>{{ __('pos.thank_you') }}</small>
        </div>
    `;

            document.getElementById('invoiceBody').innerHTML = invoiceHtml;

            // Generate QR code encoding key invoice data for verification
            if (typeof QRCode !== 'undefined') {
                const qrData = [
                    POS_SETTINGS.storeName,
                    invoice.invoice_number,
                    invoice.created_at || new Date().toISOString(),
                    String(invoice.final_total),
                    String(invoice.tax_amount || 0),
                ].join('\n');
                const canvas = document.getElementById('invoiceQrCanvas');
                if (canvas) {
                    QRCode.toCanvas(canvas, qrData, { width: 110, margin: 1, errorCorrectionLevel: 'M' }, function() {});
                }
            }

            const waBtn = document.getElementById('waInvoiceBtn');
            if (waBtn) {
                waBtn.classList.toggle('d-none', !(POS_SETTINGS.waEnabled && invoice.customer_phone));
            }

            new bootstrap.Modal(document.getElementById('invoiceModal')).show();
        }

        async function sendInvoiceWhatsApp() {
            if (!currentInvoice) return;
            const btn = document.getElementById('waInvoiceBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ app()->getLocale() === 'ar' ? 'جاري الإرسال...' : 'Sending...' }}';
            try {
                const res = await apiCall(`/api/whatsapp/invoices/${currentInvoice.id}/send`, 'POST', {});
                if (res.success) {
                    showToast('{{ app()->getLocale() === 'ar' ? 'تم إرسال الفاتورة عبر واتساب' : 'Invoice sent via WhatsApp' }}');
                    btn.classList.add('d-none');
                } else {
                    showToast(res.message || '{{ app()->getLocale() === 'ar' ? 'فشل الإرسال' : 'Send failed' }}', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-whatsapp me-2"></i>{{ app()->getLocale() === 'ar' ? 'إرسال واتساب' : 'Send WhatsApp' }}';
                }
            } catch {
                showToast('{{ app()->getLocale() === 'ar' ? 'خطأ في الإرسال' : 'Send error' }}', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp me-2"></i>{{ app()->getLocale() === 'ar' ? 'إرسال واتساب' : 'Send WhatsApp' }}';
            }
        }

        function printInvoice() {
            if (!currentInvoice) {
                showToast('لا توجد فاتورة للطباعة', 'danger');
                return;
            }
            const printableHtml = generatePrintableInvoice(currentInvoice);
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printableHtml);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.onafterprint = function() {
                printWindow.close();
            };
        }

        function generatePrintableInvoice(invoice) {
            const isRTL = document.documentElement.dir === 'rtl' || document.documentElement.lang === 'ar';
            const direction = isRTL ? 'rtl' : 'ltr';
            const textAlignHead = isRTL ? 'right' : 'left';
            const textAlignPrice = 'right'; // الأسعار دائماً باليمين

            // ✅ إعداد التواريخ
            const now = new Date();
            const dateOptions = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            };
            const timeOptions = {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const formattedDate = now.toLocaleDateString(isRTL ? 'ar-EG' : 'en-EG', dateOptions);
            const formattedTime = now.toLocaleTimeString(isRTL ? 'ar-EG' : 'en-EG', timeOptions);

            // ✅ قيم الفاتورة
            const subtotal   = invoice.subtotal || invoice.total || 0;
            const discount   = invoice.discount || 0;
            const tax        = invoice.tax_amount || 0;
            const finalTotal = invoice.final_total || (subtotal - discount + tax);
            // cash_received وchange_amount جايين من الـ API مباشرة
            const paid   = invoice.cash_received ?? finalTotal;
            const change = invoice.change_amount ?? 0;
            const cashierName = invoice.cashier_name || 'مسؤول المخزون';

            // ✅ ترجمة المحتوى
            const labels = {
                header: isRTL ? 'نظام نقطة البيع' : 'POS System',
                invoiceNo: isRTL ? 'رقم الفاتورة' : 'Invoice No',
                date: isRTL ? 'التاريخ' : 'Date',
                time: isRTL ? 'الوقت' : 'Time',
                product: isRTL ? 'اسم المنتج' : 'Product',
                qty: isRTL ? 'الكمية' : 'Qty',
                price: isRTL ? 'سعر الوحدة' : 'Price',
                total: isRTL ? 'الإجمالي' : 'Total',
                subtotalLabel: isRTL ? 'المجموع الفرعي' : 'Subtotal',
                discountLabel: isRTL ? 'الخصم' : 'Discount',
                taxLabel: isRTL ? 'الضريبة' : 'Tax',
                finalLabel: isRTL ? 'الإجمالي النهائي' : 'Grand Total',
                paidLabel: isRTL ? 'المدفوع' : 'Paid',
                changeLabel: isRTL ? 'الباقي' : 'Change',
                paymentMethod: isRTL ? 'طريقة الدفع' : 'Payment Method',
                cashier: isRTL ? 'أمين الصندوق' : 'Cashier',
                thankYou: isRTL ? 'شكراً لتسوقكم معنا' : 'Thank you for shopping with us'
            };

            // ✅ بناء جدول المنتجات
            const itemsRows = invoice.items.map(item => `
        <tr>
            <td style="padding:6px 4px; border-bottom:1px solid #ccc; text-align:${textAlignHead};">${escapeHtml(item.product_name)}</td>
            <td style="padding:6px 4px; border-bottom:1px solid #ccc; text-align:center;">${item.quantity}${item.unit_abbreviation ? ` <small style="color:#666">${escapeHtml(item.unit_abbreviation)}</small>` : ''}</td>
            <td style="padding:6px 4px; border-bottom:1px solid #ccc; text-align:${textAlignPrice};">${formatCurrency(item.price)}</td>
            <td style="padding:6px 4px; border-bottom:1px solid #ccc; text-align:${textAlignPrice};">${formatCurrency(item.subtotal)}</td>
        </tr>
    `).join('');

            // ✅ إظهار الضريبة فقط إذا كانت موجودة وفعالة
            const taxRowHtml = (POS_SETTINGS.taxEnabled && tax > 0) ? `
        <tr>
            <td colspan="3" style="padding:6px 4px; text-align:${textAlignPrice}; font-weight:bold;">${labels.taxLabel} (${POS_SETTINGS.taxRate}%)</td>
            <td style="padding:6px 4px; text-align:${textAlignPrice};">${formatCurrency(tax)}</td>
        </tr>
    ` : '';

            // ✅ إظهار الخصم فقط إذا كان موجوداً
            const discountRowHtml = (discount > 0) ? `
        <tr>
            <td colspan="3" style="padding:6px 4px; text-align:${textAlignPrice}; color:#d9534f;">${labels.discountLabel}</td>
            <td style="padding:6px 4px; text-align:${textAlignPrice}; color:#d9534f;">-${formatCurrency(discount)}</td>
        </tr>
    ` : '';

            // ✅ إظهار المدفوع والباقي — من قيم الـ API المحفوظة في DB
            const cashPaymentRows = (invoice.payment_method === 'cash' && invoice.cash_received != null) ? `
        <tr style="border-top:2px solid #333;">
            <td colspan="3" style="padding:6px 4px; text-align:${textAlignPrice}; font-weight:bold;">${labels.paidLabel}</td>
            <td style="padding:6px 4px; text-align:${textAlignPrice}; font-weight:bold; color:#198754;">${formatCurrency(invoice.cash_received)}</td>
        </tr>
        <tr style="background:#fff3cd;">
            <td colspan="3" style="padding:8px 4px; text-align:${textAlignPrice}; font-weight:bold;">${labels.changeLabel}</td>
            <td style="padding:8px 4px; text-align:${textAlignPrice}; font-weight:bold; font-size:15px; color:#856404;">${formatCurrency(invoice.change_amount ?? 0)}</td>
        </tr>
    ` : '';

            // ✅ HTML كامل للطباعة
            return `<!DOCTYPE html>
    <html dir="${direction}" lang="${isRTL ? 'ar' : 'en'}">
    <head>
        <title>${labels.invoiceNo} ${invoice.invoice_number}</title>
        <meta charset="utf-8">
        <style>
            body {
                font-family: ${isRTL ? "'Cairo', 'Segoe UI', Tahoma, sans-serif" : "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"};
                font-size: 13px;
                line-height: 1.4;
                margin: 0;
                padding: 15px;
                background: #fff;
                width: 100%;
                max-width: 350px;
                margin: 0 auto;
            }
            .invoice-box {
                border: 1px solid #ddd;
                padding: 12px;
                border-radius: 5px;
            }
            .header {
                text-align: center;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 1px dashed #aaa;
            }
            .store-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .invoice-title {
                font-size: 14px;
                font-weight: bold;
                margin-top: 5px;
            }
            .info-line {
                display: flex;
                justify-content: space-between;
                margin: 4px 0;
                font-size: 12px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 12px 0;
            }
            th {
                background-color: #f2f2f2;
                padding: 6px 4px;
                font-size: 12px;
                border-bottom: 1px solid #aaa;
                text-align: ${textAlignHead};
            }
            td {
                padding: 4px;
            }
            .totals-table {
                margin-top: 8px;
                border-top: 1px solid #ccc;
            }
            .footer {
                text-align: center;
                margin-top: 15px;
                font-size: 11px;
                color: #555;
                border-top: 1px dashed #aaa;
                padding-top: 8px;
            }
            .thankyou {
                margin-top: 8px;
                font-weight: bold;
            }
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                }
                .invoice-box {
                    border: none;
                    padding: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="invoice-box">
            <div class="header">
                <div class="store-name">${escapeHtml(POS_SETTINGS.storeName || labels.header)}</div>
                <div class="invoice-title">${labels.invoiceNo}: ${invoice.invoice_number}</div>
            </div>

            <div class="info-line">
                <span>${labels.date}: ${formattedDate}</span>
                <span>${labels.time}: ${formattedTime}</span>
            </div>

            <!-- Table header -->
            <table>
                <thead>
                    <tr>
                        <th style="text-align:${textAlignHead};">${labels.product}</th>
                        <th style="text-align:center;">${labels.qty}</th>
                        <th style="text-align:${textAlignPrice};">${labels.price}</th>
                        <th style="text-align:${textAlignPrice};">${labels.total}</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsRows}
                </tbody>
            </table>

            <!-- Totals section -->
            <table class="totals-table">
                <tr>
                    <td colspan="3" style="text-align:${textAlignPrice}; font-weight:bold;">${labels.subtotalLabel}</td>
                    <td style="text-align:${textAlignPrice};">${formatCurrency(subtotal)}</td>
                </tr>
                ${discountRowHtml}
                ${taxRowHtml}
                <tr style="border-top:1px solid #aaa; font-weight:bold;">
                    <td colspan="3" style="text-align:${textAlignPrice};">${labels.finalLabel}</td>
                    <td style="text-align:${textAlignPrice};">${formatCurrency(finalTotal)}</td>
                </tr>
                ${cashPaymentRows}
            </table>

            <!-- Payment & Cashier -->
            <div style="margin-top:12px;">
                <div class="info-line">
                    <span>${labels.paymentMethod}:</span>
                    <span>${getPaymentMethodText(invoice.payment_method)}</span>
                </div>
                <div class="info-line">
                    <span>${labels.cashier}:</span>
                    <span>${escapeHtml(cashierName)}</span>
                </div>
            </div>

            <div class="footer">
                ${POS_SETTINGS.invoiceFooter ? `<div>${escapeHtml(POS_SETTINGS.invoiceFooter)}</div>` : ''}
                <div class="thankyou">${labels.thankYou}</div>
            </div>
        </div>
    </body>
    </html>`;
        }

        function getTaxName() {
            const isArabic = document.documentElement.lang === 'ar' || document.documentElement.dir === 'rtl';
            return isArabic ? POS_SETTINGS.taxNameAr : POS_SETTINGS.taxNameEn;
        }

        function getPaymentMethodText(method) {
            const isArabic = document.documentElement.lang === 'ar' || document.documentElement.dir === 'rtl';
            const methods = {
                'cash': isArabic ? 'نقدي' : '{{ __('pos.cash') }}',
                'card': isArabic ? 'بطاقة' : '{{ __('pos.card') }}',
                'transfer': isArabic ? 'تحويل بنكي' : '{{ __('pos.transfer') }}'
            };
            return methods[method] || method;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function newSale() {
            cart = [];
            currentInvoice = null;
            clearCustomer();
            document.getElementById('discountInput').value = 0;
            document.getElementById('cashReceived').value = '';
            const notesEl = document.getElementById('invoiceNotes');
            if (notesEl) notesEl.value = '';
            autoSaveDraftId = null;
            document.getElementById('autoSaveStatus').textContent = '';
            renderCart();
            const modal = bootstrap.Modal.getInstance(document.getElementById('invoiceModal'));
            if (modal) modal.hide();
            setTimeout(() => document.getElementById('searchInput').focus(), 300);
        }

        // ─── BEEP SOUND (scanner feedback) ───────────────────────────────────────────
        function beep() {
            try {
                const ctx = new(window.AudioContext || window.webkitAudioContext)();
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.connect(g);
                g.connect(ctx.destination);
                o.frequency.value = 880;
                o.type = 'sine';
                g.gain.setValueAtTime(0.3, ctx.currentTime);
                g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                o.start(ctx.currentTime);
                o.stop(ctx.currentTime + 0.15);
            } catch (e) {
                // Audio context might be blocked by browser
            }
        }

        // ─── HELPER FUNCTIONS ─────────────────────────────────────────────────────────
        function formatCurrency(amount) {
            const symbol = POS_SETTINGS.currencySymbol;
            const isArabic = document.documentElement.lang === 'ar' || document.documentElement.dir === 'rtl';
            if (isArabic) {
                // Arabic format: symbol after number
                return `${parseFloat(amount).toFixed(2)} ${symbol}`;
            }
            return `${symbol} ${parseFloat(amount).toFixed(2)}`;
        }

        async function apiCall(url, method = 'GET', data = null) {
            const options = {
                method: method,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            };
            if (data) options.body = JSON.stringify(data);

            const response = await fetch(url, options);
            return await response.json();
        }

        // ─── CAMERA BARCODE SCANNER (html5-qrcode — same library as warehouse) ──────

        let _posHtml5QrCode = null;

        function openCameraModal() {
            new bootstrap.Modal(document.getElementById('cameraScanModal')).show();
        }

        // Lazy-load html5-qrcode (shared CDN URL with warehouse page — browser caches it)
        function _loadHtml5QrCode() {
            return new Promise((resolve, reject) => {
                if (window.Html5Qrcode) { resolve(); return; }
                const s    = document.createElement('script');
                s.src      = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
                s.onload   = resolve;
                s.onerror  = reject;
                document.head.appendChild(s);
            });
        }

        // Start camera once the modal is fully visible
        document.getElementById('cameraScanModal').addEventListener('shown.bs.modal', async function () {
            const readerEl = document.getElementById('posScannerReader');
            readerEl.innerHTML = '';
            document.getElementById('posManualBarcodeInput').value = '';

            try {
                await _loadHtml5QrCode();
            } catch (_) {
                readerEl.innerHTML = `<div class="text-center py-4 text-danger p-3">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
                    <span class="small">${LOCALE === 'ar' ? 'فشل تحميل مكتبة الماسح.' : 'Failed to load scanner library.'}</span>
                </div>`;
                return;
            }

            _posHtml5QrCode = new Html5Qrcode('posScannerReader');
            _posHtml5QrCode.start(
                { facingMode: 'environment' },              // rear camera on mobile
                { fps: 10, qrbox: { width: 280, height: 120 } },
                _onPosScanSuccess,
                null                                        // suppress per-frame errors
            ).catch(() => {
                readerEl.innerHTML = `<div class="text-center py-4 text-muted p-3">
                    <i class="fas fa-camera-slash fa-2x mb-2 d-block"></i>
                    <span class="small">${LOCALE === 'ar'
                        ? 'لا يمكن الوصول للكاميرا. استخدم الإدخال اليدوي أدناه.'
                        : 'Camera unavailable. Use manual input below.'}</span>
                </div>`;
            });

            // Focus manual input so USB physical scanners send straight into it
            setTimeout(() => document.getElementById('posManualBarcodeInput').focus(), 350);
        });

        // Stop camera when modal starts closing
        document.getElementById('cameraScanModal').addEventListener('hide.bs.modal', function () {
            if (_posHtml5QrCode) {
                _posHtml5QrCode.stop().catch(() => {});
                _posHtml5QrCode = null;
            }
        });

        // Called by html5-qrcode on a successful frame decode
        function _onPosScanSuccess(code) {
            if (_posHtml5QrCode) {
                _posHtml5QrCode.stop().catch(() => {});
                _posHtml5QrCode = null;
            }
            if (POS_SETTINGS.posSound) beep();
            _processPosBarcode(code);
        }

        // Manual input — Enter key (physical USB scanner sends code + Enter)
        document.getElementById('posManualBarcodeInput').addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const v = this.value.trim();
            if (!v) return;
            if (_posHtml5QrCode) { _posHtml5QrCode.stop().catch(() => {}); _posHtml5QrCode = null; }
            _processPosBarcode(v);
        });

        // Manual input — search button
        document.getElementById('posManualSearchBtn').addEventListener('click', function () {
            const v = document.getElementById('posManualBarcodeInput').value.trim();
            if (!v) return;
            if (_posHtml5QrCode) { _posHtml5QrCode.stop().catch(() => {}); _posHtml5QrCode = null; }
            _processPosBarcode(v);
        });

        // Core: close camera modal first, then search (avoids Bootstrap backdrop conflict)
        function _processPosBarcode(code) {
            const cameraModalEl   = document.getElementById('cameraScanModal');
            const cameraModalInst = bootstrap.Modal.getInstance(cameraModalEl);

            function _run() {
                document.getElementById('searchInput').value = code;
                handleSearch(code, true);
            }

            if (cameraModalInst) {
                cameraModalEl.addEventListener('hidden.bs.modal', _run, { once: true });
                cameraModalInst.hide();
            } else {
                _run();
            }
        }

        // ─── EVENT DELEGATION: cart table ────────────────────────────────────────
        document.getElementById('cartBody').addEventListener('click', function(e) {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const idx = parseInt(el.dataset.idx);
            const action = el.dataset.action;
            if (action === 'dec-qty') changeQty(idx, -1);
            else if (action === 'inc-qty') changeQty(idx, 1);
            else if (action === 'remove') removeItem(idx);
        });

        document.getElementById('cartBody').addEventListener('change', function(e) {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const idx = parseInt(el.dataset.idx);
            if (el.dataset.action === 'set-qty') setQty(idx, el.value);
        });

        // ─── EVENT DELEGATION: search dropdown ───────────────────────────────────
        document.getElementById('searchResults').addEventListener('click', function(e) {
            const item = e.target.closest('[data-product-idx]');
            if (item) { selectProduct(lastSearchResults[parseInt(item.dataset.productIdx)]); return; }
            if (e.target.closest('#posAddFromSearchOption')) {
                closeSearch();
                openAddProductModal(document.getElementById('searchInput').value.trim(), false);
            }
        });

        // ─── STATIC BUTTON LISTENERS ─────────────────────────────────────────────
        document.getElementById('cameraScanBtn').addEventListener('click', openCameraModal);
        document.getElementById('posAddProductBtn').addEventListener('click', function() {
            openAddProductModal(document.getElementById('searchInput').value.trim(), false);
        });
        document.getElementById('searchTriggerBtn').addEventListener('click', triggerSearch);
        document.getElementById('clearCartBtn').addEventListener('click', clearCart);
        document.getElementById('discountInput').addEventListener('change', updateTotals);
        document.getElementById('btnCash').addEventListener('click', () => setPayment('cash'));
        document.getElementById('btnCard').addEventListener('click', () => setPayment('card'));
        document.getElementById('btnTransfer').addEventListener('click', () => setPayment('transfer'));
        document.getElementById('cashReceived').addEventListener('input', calcChange);
        document.getElementById('completeSaleBtn').addEventListener('click', completeSale);
        document.getElementById('printInvoiceBtn').addEventListener('click', printInvoice);
        document.getElementById('newSaleBtn').addEventListener('click', newSale);
        const waInvoiceBtn = document.getElementById('waInvoiceBtn');
        if (waInvoiceBtn) waInvoiceBtn.addEventListener('click', sendInvoiceWhatsApp);

        // ─── AUTO-SAVE DRAFT ──────────────────────────────────────────────────────
        let autoSaveDraftId = null;
        let autoSaveTimer = null;

        async function autoSaveDraft() {
            if (!cart.length) return;
            try {
                const notes = (document.getElementById('invoiceNotes')?.value || '').trim();
                const payload = {
                    items: cart.map(i => ({
                        product_id:   i.product_id,
                        product_name: i.product_name,
                        quantity:     i.quantity,
                        price:        i.price,
                    })),
                    discount:       parseFloat(document.getElementById('discountInput').value) || 0,
                    payment_method: paymentMethod,
                    notes:          notes || null,
                };
                const res = await fetch('{{ route('held-invoices.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                if (res.ok) {
                    const data = await res.json();
                    autoSaveDraftId = data.id ?? autoSaveDraftId;
                    const ts = new Date().toLocaleTimeString(document.documentElement.dir === 'rtl' ? 'ar-EG' : 'en-EG', { hour: '2-digit', minute: '2-digit' });
                    document.getElementById('autoSaveStatus').textContent = (document.documentElement.dir === 'rtl' ? 'حُفظ تلقائياً ' : 'Auto-saved ') + ts;
                }
            } catch (_) {}
        }

        function scheduleAutoSave() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(autoSaveDraft, 3000);
        }

        // Trigger auto-save whenever cart changes (patch renderCart)
        const _origRenderCart = renderCart;
        renderCart = function() {
            _origRenderCart.apply(this, arguments);
            if (cart.length) scheduleAutoSave();
        };

        // ─── KEYBOARD SHORTCUTS ───────────────────────────────────────────────────
        document.addEventListener('keydown', function(e) {
            const tag = document.activeElement?.tagName;
            const inInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag);

            // F1 or /  → focus search
            if (e.key === 'F1' || (!inInput && e.key === '/')) {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            // F9 or F3 → complete sale
            if ((e.key === 'F9' || e.key === 'F3') && !inInput) {
                e.preventDefault();
                if (!document.getElementById('completeSaleBtn').disabled) completeSale();
            }
            // F2 → focus discount
            if (e.key === 'F2') {
                e.preventDefault();
                document.getElementById('discountInput').focus();
            }
            // Escape → clear search dropdown (or close modals handled by Bootstrap)
            if (e.key === 'Escape' && !inInput) {
                closeSearch();
            }
            // Backspace / Delete → clear cart (only when not in input and cart not empty)
            if ((e.key === 'Delete') && !inInput && cart.length) {
                e.preventDefault();
                if (confirm(document.documentElement.dir === 'rtl' ? 'مسح الفاتورة؟' : 'Clear cart?')) clearCart();
            }
        });

        // ─── ONLINE/OFFLINE STATUS ────────────────────────────────────────────────
        function updateOnlineStatus() {
            const badge = document.getElementById('systemStatusBadge');
            if (!badge) return;
            if (navigator.onLine) {
                badge.className = 'badge bg-success';
                badge.innerHTML = '<i class="fas fa-circle me-1" style="font-size:8px;"></i>' + (document.documentElement.dir === 'rtl' ? 'متصل' : 'Online');
            } else {
                badge.className = 'badge bg-danger';
                badge.innerHTML = '<i class="fas fa-circle me-1" style="font-size:8px;"></i>' + (document.documentElement.dir === 'rtl' ? 'غير متصل' : 'Offline');
            }
        }
        window.addEventListener('online', () => {
            updateOnlineStatus();
            syncOfflineQueue();
        });
        window.addEventListener('offline', () => {
            updateOnlineStatus();
        });
        updateOnlineStatus();

        // ─── OFFLINE QUEUE ────────────────────────────────────────────────────────
        async function updateOfflineQueueBadge() {
            const queue   = await PosDB.getQueue().catch(() => []);
            const banner  = document.getElementById('offlineQueueBanner');
            const counter = document.getElementById('offlineQueueCount');
            if (queue.length > 0) {
                counter.textContent = queue.length;
                banner.classList.remove('d-none');
            } else {
                banner.classList.add('d-none');
            }
        }

        async function syncOfflineQueue() {
            if (!navigator.onLine) return;
            const queue = await PosDB.getQueue().catch(() => []);
            if (!queue.length) return;

            const isRTL = document.documentElement.dir === 'rtl';

            // Batch all pending invoices into one request
            const invoices = queue.map(item => item.payload);

            let json;
            try {
                const res = await fetch('{{ route('offline.sync') }}', {
                    method:      'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                    },
                    body: JSON.stringify({ invoices }),
                });
                json = await res.json();
            } catch {
                // Still offline — leave queue intact
                return;
            }

            if (!json.success) {
                showToast(isRTL ? 'فشلت المزامنة' : 'Sync failed', 'danger');
                return;
            }

            // Remove successfully synced/skipped items from local queue
            const syncedUuids = new Set(
                (json.results || [])
                    .filter(r => r.status === 'synced' || r.status === 'already_synced')
                    .map(r => r.offline_uuid)
            );
            for (const item of queue) {
                if (syncedUuids.has(item.offline_uuid)) {
                    await PosDB.removeFromQueue(item.id);
                }
            }

            await updateOfflineQueueBadge();

            const synced  = json.synced  ?? 0;
            const skipped = json.skipped ?? 0;
            const failed  = json.failed  ?? 0;

            if (synced + skipped > 0) {
                showToast(
                    isRTL ? `تمت مزامنة ${synced + skipped} فاتورة` : `${synced + skipped} invoice(s) synced`,
                    'success'
                );
            }
            if (failed > 0) {
                showToast(
                    isRTL ? `فشلت مزامنة ${failed} فاتورة` : `${failed} invoice(s) failed to sync`,
                    'danger'
                );
            }
        }

        // ─── PRODUCT CACHE WARM-UP ────────────────────────────────────────────────
        async function warmProductCache() {
            if (!navigator.onLine) return;
            try {
                const res = await fetch('{{ route('products.for-cache') }}', {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                });
                const json = await res.json();
                if (json.success && json.products?.length) {
                    await PosDB.cacheProducts(json.products);
                }
            } catch { /* silent — cache is best-effort */ }
        }

        // ─── SERVICE WORKER REGISTRATION ─────────────────────────────────────────
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }

        // ─── PRICE LOCK FOR CASHIER ───────────────────────────────────────────────
        if (!POS_SETTINGS.allowPriceChange) {
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.price-override-input').forEach(el => el.setAttribute('readonly', true));
            });
        }

        // ─── QUICK-ADD PRODUCT FROM POS ──────────────────────────────────────────
        // Holds categories already loaded in POS session (from lastSearchResults + any cached list)
        const _posKnownCategories = new Set();

        /**
         * Open the quick-add modal.
         * @param {string} prefillBarcode  - barcode value to pre-fill (empty string = blank form)
         * @param {boolean} fromFailedScan - true when triggered by a barcode that wasn't found
         */
        function openAddProductModal(prefillBarcode, fromFailedScan) {
            const isAr = LOCALE === 'ar';

            // Reset form
            document.getElementById('posNewName').value      = '';
            document.getElementById('posNewPrice').value     = '';
            document.getElementById('posNewCostPrice').value = '';
            document.getElementById('posNewBarcode').value   = prefillBarcode || '';
            document.getElementById('posNewCategory').value  = '';
            document.getElementById('posNewQuantity').value  = 0;
            document.getElementById('posNewMinStock').value  = 5;
            document.getElementById('posAddToCartAfterSave').checked = true;
            document.getElementById('posAddProductError').classList.add('d-none');
            document.getElementById('posNameLookupBadge').classList.add('d-none');
            document.getElementById('posNameFoundBadge').classList.add('d-none');

            // Show / hide "barcode not found" notice
            const notice = document.getElementById('posBarcodeNotFoundAlert');
            if (fromFailedScan && prefillBarcode) {
                notice.classList.remove('d-none');
            } else {
                notice.classList.add('d-none');
            }

            // Populate category datalist from known categories
            const dl = document.getElementById('posCategoryList');
            dl.innerHTML = [..._posKnownCategories].map(c => `<option value="${escapeHtml(c)}">`).join('');

            // Show modal
            new bootstrap.Modal(document.getElementById('posAddProductModal')).show();

            // Focus name field after animation
            setTimeout(() => document.getElementById('posNewName').focus(), 350);

            // Auto-lookup product name via server-side proxy (avoids CSP connect-src block)
            if (fromFailedScan && prefillBarcode && /^\d{6,14}$/.test(prefillBarcode)) {
                _lookupBarcodeName(prefillBarcode);
            }
        }

        /**
         * Fetch product name from the server-side barcode proxy.
         * The proxy tries Open Food Facts, then UPC Item DB — all server-side,
         * so there is no CSP connect-src issue.
         * Fills the name field only if the user hasn't typed yet.
         */
        async function _lookupBarcodeName(barcode) {
            const nameEl    = document.getElementById('posNewName');
            const spinnerEl = document.getElementById('posNameLookupBadge');
            const foundEl   = document.getElementById('posNameFoundBadge');

            spinnerEl.classList.remove('d-none');

            try {
                const res  = await fetch(
                    `{{ url('/api/barcode-lookup') }}/${encodeURIComponent(barcode)}`,
                    {
                        headers: {
                            'Accept':       'application/json',
                            'X-CSRF-TOKEN': CSRF_TOKEN,
                        },
                        credentials: 'same-origin',
                    }
                );
                const json = await res.json();

                spinnerEl.classList.add('d-none');

                const name = json.name?.trim();
                if (name && !nameEl.value.trim()) {
                    nameEl.value = name;
                    foundEl.classList.remove('d-none');
                    // Pre-fill brand as category hint if category is still empty
                    if (!document.getElementById('posNewCategory').value && json.brand) {
                        document.getElementById('posNewCategory').value = json.brand;
                    }
                }
            } catch (_) {
                spinnerEl.classList.add('d-none');
            }
        }

        // Collect categories from search results to power the datalist
        const _origRenderDropdown = renderSearchDropdown;
        renderSearchDropdown = function(products) {
            products.forEach(p => { if (p.category) _posKnownCategories.add(p.category); });
            _origRenderDropdown(products);
        };

        // Save new product handler
        document.getElementById('posSaveNewProductBtn').addEventListener('click', async function () {
            const isAr   = LOCALE === 'ar';
            const name   = document.getElementById('posNewName').value.trim();
            const price  = parseFloat(document.getElementById('posNewPrice').value);
            const errEl  = document.getElementById('posAddProductError');

            errEl.classList.add('d-none');

            if (!name) {
                errEl.textContent = isAr ? 'اسم المنتج مطلوب.' : 'Product name is required.';
                errEl.classList.remove('d-none');
                document.getElementById('posNewName').focus();
                return;
            }
            if (!price || price <= 0) {
                errEl.textContent = isAr ? 'سعر البيع مطلوب ويجب أن يكون أكبر من صفر.' : 'Selling price is required and must be greater than 0.';
                errEl.classList.remove('d-none');
                document.getElementById('posNewPrice').focus();
                return;
            }

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + (isAr ? 'جاري الحفظ...' : 'Saving...');

            const payload = {
                name:             name,
                price:            price,
                cost_price:       parseFloat(document.getElementById('posNewCostPrice').value) || 0,
                barcode:          document.getElementById('posNewBarcode').value.trim() || null,
                category:         document.getElementById('posNewCategory').value.trim() || null,
                initial_quantity: parseInt(document.getElementById('posNewQuantity').value) || 0,
                min_stock:        parseInt(document.getElementById('posNewMinStock').value) || 5,
                is_active:        true,
            };

            try {
                const res = await apiCall('{{ route("products.store") }}', 'POST', payload);

                if (res.success || res.product) {
                    const savedProduct = res.product ?? res;

                    // Track category for future datalist suggestions
                    if (savedProduct.category) _posKnownCategories.add(savedProduct.category);

                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('posAddProductModal')).hide();

                    // Clear search bar
                    document.getElementById('searchInput').value = '';

                    showToast(isAr ? `✅ تم حفظ "${name}"` : `✅ "${name}" saved`);

                    // Add to cart if checkbox is checked
                    if (document.getElementById('posAddToCartAfterSave').checked) {
                        // Build a minimal product object compatible with addToCart()
                        const cartProduct = {
                            id:       savedProduct.id,
                            name:     savedProduct.name     ?? name,
                            price:    savedProduct.price    ?? price,
                            quantity: savedProduct.quantity ?? (payload.initial_quantity || 1),
                            min_stock: savedProduct.min_stock ?? 5,
                            barcode:   savedProduct.barcode  ?? null,
                            category:  savedProduct.category ?? null,
                            unit_abbreviation: savedProduct.unit_abbreviation ?? null,
                            unit_name:         savedProduct.unit_name         ?? null,
                        };
                        // Allow adding even if initial qty = 0 (cashier created product on the fly)
                        if (cartProduct.quantity <= 0) cartProduct.quantity = 1;
                        addToCart(cartProduct);
                        if (POS_SETTINGS.posSound) beep();
                    }

                } else {
                    errEl.textContent = res.message || (isAr ? 'حدث خطأ أثناء الحفظ.' : 'An error occurred while saving.');
                    errEl.classList.remove('d-none');
                }
            } catch (e) {
                errEl.textContent = isAr ? 'تعذّر الاتصال بالخادم.' : 'Could not reach the server.';
                errEl.classList.remove('d-none');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-floppy-disk me-1"></i>${isAr ? 'حفظ المنتج' : 'Save Product'}`;
            }
        });

        // Allow Enter in Name/Price fields to trigger save
        ['posNewName', 'posNewPrice'].forEach(id => {
            document.getElementById(id).addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('posSaveNewProductBtn').click();
                }
            });
        });

        // ─── INIT ─────────────────────────────────────────────────────────────────
        setPayment(POS_SETTINGS.defaultPayment);
        document.addEventListener('click', e => {
            if (!e.target.closest('.product-search')) closeSearch();
        });
        document.getElementById('searchInput').focus();
        warmProductCache();
        updateOfflineQueueBadge();
    </script>
@endpush
