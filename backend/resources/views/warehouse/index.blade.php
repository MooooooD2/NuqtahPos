{{--
=============================================================
REMAINING BLADE VIEWS - باقي الواجهات
=============================================================
Each file listed below with its path and content summary.
Full implementations follow the same pattern as pos/index.blade.php
=============================================================
--}}

{{-- ============================================================
FILE: resources/views/warehouse/index.blade.php
DESCRIPTION: Product management with search, CRUD, stock management
============================================================ --}}
@extends('layouts.app')
@section('title', __('pos.warehouse'))
@section('page-title', __('pos.warehouse'))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-boxes me-2"></i>{{ __('pos.warehouse') }}</span>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#unitsModal">
                <i class="fas fa-ruler me-1"></i>{{ __('pos.manage_units') }}
            </button>
            <button class="btn btn-outline-success btn-sm" data-fn="openBarcodeScanner">
                <i class="fas fa-barcode me-1"></i>{{ app()->getLocale() === 'ar' ? 'مسح باركود' : 'Scan Barcode' }}
            </button>
            {{-- ── Import button ── --}}
            <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#importProductsModal">
                <i class="fas fa-file-excel me-1"></i>{{ __('pos.import_products') }}
            </button>
            {{-- ── Export button ── --}}
            <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#exportProductsModal">
                <i class="fas fa-file-download me-1"></i>{{ __('pos.export_products') }}
            </button>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus me-1"></i>{{ __('pos.add_product') }}
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control" id="productSearch"
                    placeholder="{{ __('pos.search') }}..." data-on-input="filterProducts">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="categoryFilter" data-on-change="filterProducts">
                    <option value="">{{ __('pos.category') }} - {{ __('pos.filter') }}</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" id="stockFilter" data-on-change="filterProducts">
                    <option value="">{{ __('pos.status') }} - {{ __('pos.filter') }}</option>
                    <option value="low">{{ __('pos.low_stock') }}</option>
                    <option value="out">{{ __('pos.out_of_stock') }}</option>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>{{ __('pos.product_name') }}</th>
                        <th>{{ __('pos.barcode') }}</th>
                        <th>{{ __('pos.category') }}</th>
                        <th>{{ __('pos.unit') }}</th>
                        <th>{{ __('pos.selling_price') }}</th>
                        <th>{{ __('pos.cost_price') }}</th>
                        <th>{{ __('pos.current_stock') }}</th>
                        <th>{{ __('pos.status') }}</th>
                        <th>{{ __('pos.actions') }}</th>
                    </tr>
                </thead>
                <tbody id="productsBody">
                    <tr><td colspan="10" class="text-center py-4"><div class="spinner-border"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Add/Edit Product Modal --}}
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">{{ __('pos.add_product') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="productId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">{{ __('pos.product_name') }} *</label>
                        <input type="text" class="form-control" id="productName" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.selling_price') }} *</label>
                        <input type="number" class="form-control" id="productPrice" step="0.01" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.cost_price') }}</label>
                        <input type="number" class="form-control" id="productCostPrice" step="0.01" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.current_stock') }}</label>
                        <input type="number" class="form-control" id="productQuantity" min="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.min_stock') }}</label>
                        <input type="number" class="form-control" id="productMinStock" min="0" value="5">
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.barcode') }}</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="productBarcode"
                                placeholder="{{ app()->getLocale() === 'ar' ? 'أدخل أو امسح الباركود' : 'Enter or scan barcode' }}">
                            <button type="button" class="btn btn-outline-secondary"
                                title="{{ app()->getLocale() === 'ar' ? 'مسح بالكاميرا' : 'Scan with camera' }}"
                                data-fn="openBarcodeScanner" data-args='["field","productBarcode"]'>
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.category') }}</label>
                        <input type="text" class="form-control" id="productCategory">
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.unit') }}</label>
                        <select class="form-select" id="productUnitId">
                            <option value="">{{ __('pos.no_unit') }}</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">{{ __('pos.suppliers') }}</label>
                        <input type="text" class="form-control" id="productSupplier">
                    </div>
                    <div class="col-12" id="productWarehouseRow">
                        <label class="form-label">{{ __('pos.warehouse') }}</label>
                        <select class="form-select" id="productWarehouseId">
                            <option value="">{{ app()->getLocale() === 'ar' ? 'المخزن الافتراضي' : 'Default warehouse' }}</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveProduct">{{ __('pos.save') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Add Stock Modal --}}
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pos.add_stock') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="stockProductId">
                <p id="stockProductName" class="fw-semibold mb-3"></p>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.quantity') }} *</label>
                    <input type="number" class="form-control" id="stockQuantity" min="1" value="1">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.warehouse') }}</label>
                    <select class="form-select" id="stockWarehouseId">
                        <option value="">{{ app()->getLocale() === 'ar' ? 'المخزن الافتراضي' : 'Default warehouse' }}</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.notes') }}</label>
                    <input type="text" class="form-control" id="stockReason">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-success" data-fn="submitAddStock">{{ __('pos.add_stock') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- ─── BARCODE MODAL ──────────────────────────────────────────────────────── --}}
<div class="modal fade" id="barcodeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-barcode me-2"></i>{{ app()->getLocale() === 'ar' ? 'باركود المنتج' : 'Product Barcode' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4" id="barcodeModalBody">
                <p id="barcodeProductName" class="fw-bold mb-1"></p>
                <p id="barcodeProductPrice" class="text-success mb-3"></p>
                <div id="barcodeContainer" class="d-flex justify-content-center mb-2">
                    <canvas id="barcodeCanvas"></canvas>
                </div>
                <p id="barcodeValue" class="text-muted small font-monospace mb-3"></p>
                <div id="barcodeGenerateSection" class="d-none">
                    <p class="text-warning small">{{ app()->getLocale() === 'ar' ? 'لا يوجد باركود، قم بتوليد واحد:' : 'No barcode. Generate one:' }}</p>
                    <button class="btn btn-sm btn-outline-primary" data-fn="generateBarcode">
                        <i class="fas fa-magic me-1"></i>{{ app()->getLocale() === 'ar' ? 'توليد باركود' : 'Generate Barcode' }}
                    </button>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button class="btn btn-success" data-fn="printBarcode">
                    <i class="fas fa-print me-1"></i>{{ app()->getLocale() === 'ar' ? 'طباعة' : 'Print' }}
                </button>
                <button class="btn btn-outline-secondary" data-fn="downloadBarcode">
                    <i class="fas fa-download me-1"></i>{{ app()->getLocale() === 'ar' ? 'تحميل' : 'Download' }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Units Management Modal --}}
<div class="modal fade" id="unitsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ruler me-2"></i>{{ __('pos.manage_units') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Add / Edit form --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <input type="hidden" id="unitId">
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-5">
                                <label class="form-label">{{ __('pos.unit_name') }} *</label>
                                <input type="text" class="form-control" id="unitName" placeholder="{{ __('pos.unit_name') }}">
                            </div>
                            <div class="col-12 col-sm-4">
                                <label class="form-label">{{ __('pos.unit_abbreviation') }}</label>
                                <input type="text" class="form-control" id="unitAbbreviation" placeholder="{{ app()->getLocale() === 'ar' ? 'مثال: كجم، لتر' : 'e.g. kg, L' }}">
                            </div>
                            <div class="col-12 col-sm-3">
                                <button class="btn btn-primary w-100" data-fn="saveUnit">
                                    <i class="fas fa-save me-1"></i>{{ __('pos.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Units list --}}
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>{{ __('pos.unit_name') }}</th>
                                <th>{{ __('pos.unit_abbreviation') }}</th>
                                <th>{{ __('pos.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="unitsBody">
                            <tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ─── BARCODE SCANNER MODAL ──────────────────────────────────────────────── --}}
<div class="modal fade" id="barcodeScannerModal" tabindex="-1">
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
                {{-- Camera viewfinder (html5-qrcode renders here) --}}
                <div id="barcodeScannerReader" class="w-100 scanner-reader-box"></div>

                {{-- Manual / physical scanner fallback --}}
                <div class="p-3 border-top">
                    <p class="text-muted small mb-2">
                        <i class="fas fa-keyboard me-1"></i>
                        {{ app()->getLocale() === 'ar'
                            ? 'أو أدخل الباركود يدوياً (يدعم الماسح الضوئي الفيزيائي):'
                            : 'Or enter barcode manually (physical scanner supported):' }}
                    </p>
                    <div class="input-group">
                        <input type="text" class="form-control" id="manualBarcodeInput"
                            placeholder="{{ app()->getLocale() === 'ar' ? 'اكتب أو امسح واضغط Enter' : 'Type or scan & press Enter' }}"
                            autocomplete="off">
                        <button class="btn btn-primary" data-fn="processManualBarcode">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Dynamic result shown after scan --}}
            <div id="barcodeScanResult" class="d-none"></div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     Import Products Modal
═══════════════════════════════════════════════════════════════ --}}
@php $isAr = app()->getLocale() === 'ar'; @endphp
<div class="modal fade" id="importProductsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-file-excel text-success me-2"></i>{{ __('pos.import_products') }}
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        {{-- Step 1: Download template --}}
        <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
          <i class="fas fa-circle-info fa-lg flex-shrink-0"></i>
          <div>
            <strong>{{ $isAr ? 'الخطوة 1: تحميل القالب' : 'Step 1: Download the template' }}</strong><br>
            <small class="text-muted">
              {{ $isAr
                ? 'حمّل ملف Excel القالب، أدخل بياناتك ثم ارفع الملف أدناه.'
                : 'Download the Excel template, fill in your products, then upload below.' }}
            </small>
          </div>
          <a href="{{ route('products.import.template') }}" class="btn btn-sm btn-outline-primary ms-auto flex-shrink-0">
            <i class="fas fa-download me-1"></i>{{ __('pos.import_download_template') }}
          </a>
        </div>

        {{-- Column reference --}}
        <div class="table-responsive mb-4">
          <table class="table table-sm table-bordered align-middle" style="font-size:.82rem">
            <thead class="table-dark text-center">
              <tr>
                <th>{{ $isAr ? 'العمود' : 'Column' }}</th>
                <th>{{ $isAr ? 'مطلوب' : 'Required' }}</th>
                <th>{{ $isAr ? 'الوصف' : 'Description' }}</th>
                <th>{{ $isAr ? 'مثال' : 'Example' }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach([
                ['name (اسم_المنتج)',        true,  $isAr ? 'اسم المنتج'              : 'Product name',            $isAr ? 'أرز بسمتي 1 كيلو'   : 'Basmati Rice 1kg'],
                ['barcode (الباركود)',        false, $isAr ? 'باركود المنتج (اختياري)' : 'Product barcode',         '6281234567890'],
                ['category (الفئة)',          false, $isAr ? 'تصنيف المنتج'            : 'Category name',           $isAr ? 'حبوب'                : 'Grains'],
                ['price (السعر)',             true,  $isAr ? 'سعر البيع'               : 'Selling price',           '25.00'],
                ['cost_price (سعر_التكلفة)', false, $isAr ? 'سعر التكلفة'             : 'Cost price',              '18.00'],
                ['wholesale_price',           false, $isAr ? 'سعر الجملة'              : 'Wholesale price',         '22.00'],
                ['vip_price',                 false, $isAr ? 'سعر VIP'                 : 'VIP price',               '24.00'],
                ['min_stock (الحد_الادنى)',   false, $isAr ? 'الحد الأدنى للمخزون'    : 'Min stock alert level',   '10'],
                ['initial_qty (الكمية_الابتدائية)', false, $isAr ? 'كمية الافتتاح (للمنتجات الجديدة فقط)' : 'Opening stock (new products only)', '50'],
                ['description (الوصف)',       false, $isAr ? 'وصف المنتج'              : 'Product description',     $isAr ? 'وصف مختصر'           : 'Short description'],
                ['is_active (نشط)',           false, $isAr ? '1 = نشط ، 0 = غير نشط'  : '1 = Active, 0 = Inactive','1'],
              ] as [$col, $req, $desc, $ex])
              <tr>
                <td><code>{{ $col }}</code></td>
                <td class="text-center">
                  @if($req)
                    <span class="badge bg-danger">{{ $isAr ? 'مطلوب' : 'Required' }}</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ $desc }}</td>
                <td class="text-muted font-monospace">{{ $ex }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Step 2: Upload --}}
        <div class="mb-3">
          <label class="form-label fw-semibold">
            <i class="fas fa-upload me-1"></i>
            {{ $isAr ? 'الخطوة 2: رفع الملف (xlsx, xls, csv)' : 'Step 2: Upload file (xlsx, xls, csv)' }}
          </label>
          <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
          <div class="form-text">
            {{ $isAr ? 'الحد الأقصى: 10 MB — يمكنك استخدام أسماء الأعمدة بالعربية أو الإنجليزية.' : 'Max 10 MB — Arabic or English column names are both accepted.' }}
          </div>
        </div>

        {{-- Progress bar --}}
        <div id="importProgress" class="d-none mb-3">
          <div class="progress" style="height:8px">
            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success w-100"></div>
          </div>
          <small class="text-muted mt-1 d-block text-center">
            {{ $isAr ? 'جاري المعالجة…' : 'Processing…' }}
          </small>
        </div>

        {{-- Result area --}}
        <div id="importResult" class="d-none"></div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          {{ $isAr ? 'إغلاق' : 'Close' }}
        </button>
        <button class="btn btn-success" id="btnRunImport">
          <i class="fas fa-file-import me-1"></i>{{ $isAr ? 'استيراد الآن' : 'Import Now' }}
        </button>
      </div>

    </div>
  </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     Export Products Modal
═══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="exportProductsModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
          <i class="fas fa-file-download text-success me-2"></i>{{ __('pos.export_products') }}
        </h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <p class="text-muted small mb-4">
          {{ $isAr
            ? 'اختر الفلاتر المناسبة ثم اضغط "تصدير" لتحميل ملف Excel يحتوي على المنتجات المطلوبة.'
            : 'Apply filters, then click Export to download an Excel file with the matching products.' }}
        </p>

        <div class="row g-3">

          {{-- Category filter --}}
          <div class="col-12 col-sm-6">
            <label class="form-label fw-semibold">
              <i class="fas fa-tag me-1 text-muted"></i>{{ __('pos.category') }}
            </label>
            <select class="form-select" id="exportCategory">
              <option value="">{{ $isAr ? 'جميع الفئات' : 'All Categories' }}</option>
            </select>
          </div>

          {{-- Stock filter --}}
          <div class="col-12 col-sm-6">
            <label class="form-label fw-semibold">
              <i class="fas fa-boxes me-1 text-muted"></i>{{ __('pos.status') }}
            </label>
            <select class="form-select" id="exportStock">
              <option value="">{{ $isAr ? 'كل المخزون' : 'All Stock Levels' }}</option>
              <option value="low">{{ __('pos.low_stock') }}</option>
              <option value="out">{{ __('pos.out_of_stock') }}</option>
            </select>
          </div>

          {{-- Active filter --}}
          <div class="col-12 col-sm-6">
            <label class="form-label fw-semibold">
              <i class="fas fa-toggle-on me-1 text-muted"></i>{{ $isAr ? 'حالة المنتج' : 'Product Status' }}
            </label>
            <select class="form-select" id="exportActive">
              <option value="">{{ $isAr ? 'الكل (نشط + غير نشط)' : 'All (active & inactive)' }}</option>
              <option value="1">{{ $isAr ? 'النشطة فقط' : 'Active only' }}</option>
              <option value="0">{{ $isAr ? 'غير النشطة فقط' : 'Inactive only' }}</option>
            </select>
          </div>

          {{-- Format --}}
          <div class="col-12 col-sm-6">
            <label class="form-label fw-semibold">
              <i class="fas fa-file me-1 text-muted"></i>{{ $isAr ? 'صيغة الملف' : 'File Format' }}
            </label>
            <select class="form-select" id="exportFormat">
              <option value="xlsx">Excel (.xlsx)</option>
              <option value="csv">CSV (.csv)</option>
            </select>
          </div>

        </div>

        {{-- Info note --}}
        <div class="alert alert-light border mt-4 mb-0 d-flex align-items-start gap-2" style="font-size:.82rem">
          <i class="fas fa-info-circle text-primary mt-1 flex-shrink-0"></i>
          <span>
            {{ $isAr
              ? 'يشمل التصدير: الاسم، الباركود، الفئة، الأسعار، الكمية، الحد الأدنى، الوصف، الحالة، وتاريخ الإضافة.'
              : 'Export includes: name, barcode, category, prices, quantity, min stock, description, status, and creation date.' }}
          </span>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">
          {{ $isAr ? 'إغلاق' : 'Close' }}
        </button>
        <a id="btnExportDownload" href="#" class="btn btn-success" target="_blank">
          <i class="fas fa-download me-1"></i>{{ $isAr ? 'تصدير وتحميل' : 'Export & Download' }}
        </a>
      </div>

    </div>
  </div>
</div>

@endsection

@push('scripts')
<script @nonce>
let allProducts = [];
let renderedProducts = [];
let warehouseOpts = '';
let allUnits = [];
let unitOpts = '';

async function loadWarehouses() {
    const res = await apiCall('{{ url("/api/warehouses") }}');
    const list = Array.isArray(res) ? res : (res.data ?? []);
    warehouseOpts = list.map(w =>
        `<option value="${w.id}"${w.is_default ? ' selected' : ''}>${w.name}</option>`
    ).join('');
    ['productWarehouseId', 'stockWarehouseId'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            const blank = `<option value="">${LOCALE === 'ar' ? 'المخزن الافتراضي' : 'Default warehouse'}</option>`;
            el.innerHTML = blank + warehouseOpts;
        }
    });
}

async function loadProducts() {
    const res  = await apiCall('{{ route("products.all") }}');
    const rawP = res.products ?? [];
    allProducts = Array.isArray(rawP) ? rawP : (Array.isArray(rawP.data) ? rawP.data : []);
    populateCategoryFilter();
    renderProducts(allProducts);
}

function populateCategoryFilter() {
    const cats = [...new Set(allProducts.map(p => p.category).filter(Boolean))];
    const sel  = document.getElementById('categoryFilter');
    sel.innerHTML = `<option value="">{{ __('pos.category') }}</option>` +
        cats.map(c => `<option value="${c}">${c}</option>`).join('');
}

function filterProducts() {
    const search  = document.getElementById('productSearch').value.toLowerCase();
    const cat     = document.getElementById('categoryFilter').value;
    const stock   = document.getElementById('stockFilter').value;

    const filtered = allProducts.filter(p => {
        const matchSearch = !search || p.name.toLowerCase().includes(search) || (p.barcode || '').includes(search);
        const matchCat    = !cat    || p.category === cat;
        const matchStock  = !stock  || (stock === 'low' && p.low_stock && p.quantity > 0) || (stock === 'out' && p.quantity === 0);
        return matchSearch && matchCat && matchStock;
    });
    renderProducts(filtered);
}

function renderProducts(products) {
    renderedProducts = products;
    document.getElementById('productsBody').innerHTML = products.length
        ? products.map((p, i) => `
            <tr>
                <td>${i+1}</td>
                <td class="fw-semibold">${p.name}</td>
                <td><code>${p.barcode || '-'}</code></td>
                <td>${p.category || '-'}</td>
                <td>${p.unit_abbreviation
                    ? `<span class="badge bg-info text-dark">${p.unit_abbreviation}</span>`
                    : p.unit_name
                    ? `<span class="badge bg-secondary">${p.unit_name}</span>`
                    : '<span class="text-muted">-</span>'}</td>
                <td class="text-success fw-semibold">${formatCurrency(p.price)}</td>
                <td class="text-muted">${formatCurrency(p.cost_price)}</td>
                <td class="fw-bold ${p.quantity === 0 ? 'text-danger' : p.low_stock ? 'text-warning' : 'text-success'}">${p.quantity}</td>
                <td>
                    ${p.quantity === 0
                        ? '<span class="badge bg-danger">{{ __("pos.out_of_stock") }}</span>'
                        : p.low_stock
                        ? '<span class="badge badge-low-stock">{{ __("pos.low_stock") }}</span>'
                        : '<span class="badge badge-in-stock">OK</span>'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-warning text-white" title="باركود" data-action="barcode" data-idx="${i}"><i class="fas fa-barcode"></i></button>
                        <button class="btn btn-success" data-action="add-stock" data-idx="${i}"><i class="fas fa-plus"></i></button>
                        <button class="btn btn-primary" data-action="edit" data-idx="${i}"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger" data-action="delete" data-idx="${i}"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('')
        : '<tr><td colspan="10" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

function editProduct(p) {
    document.getElementById('productId').value        = p.id;
    document.getElementById('productName').value      = p.name;
    document.getElementById('productPrice').value     = p.price;
    document.getElementById('productCostPrice').value = p.cost_price;
    document.getElementById('productMinStock').value  = p.min_stock;
    document.getElementById('productBarcode').value   = p.barcode || '';
    document.getElementById('productCategory').value  = p.category || '';
    document.getElementById('productSupplier').value  = p.supplier || '';
    document.getElementById('productUnitId').value    = p.unit_id || '';
    const qtyInput = document.getElementById('productQuantity');
    qtyInput.value    = p.quantity ?? 0;
    qtyInput.disabled = true;
    document.getElementById('productWarehouseRow').classList.add('d-none');
    document.getElementById('productModalTitle').textContent = '{{ __("pos.edit_product") }}';
    new bootstrap.Modal(document.getElementById('addProductModal')).show();
}

async function saveProduct() {
    const id          = document.getElementById('productId').value;
    const warehouseId = document.getElementById('productWarehouseId').value;
    const unitVal = document.getElementById('productUnitId').value;
    const data = {
        name:             document.getElementById('productName').value,
        price:            document.getElementById('productPrice').value,
        cost_price:       document.getElementById('productCostPrice').value,
        initial_quantity: document.getElementById('productQuantity').value || 0,
        min_stock:        document.getElementById('productMinStock').value,
        barcode:          document.getElementById('productBarcode').value,
        category:         document.getElementById('productCategory').value,
        supplier:         document.getElementById('productSupplier').value,
        unit_id:          unitVal ? parseInt(unitVal) : null,
    };
    if (!id && warehouseId) data.warehouse_id = parseInt(warehouseId);

    const url    = id ? `/api/products/${id}` : '{{ route("products.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, data);

    if (res.success) {
        showToast('{{ __("pos.success") }}');
        bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
        document.getElementById('productId').value = '';
        document.getElementById('productQuantity').disabled = false;
        loadProducts();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

function showAddStock(id, name) {
    document.getElementById('stockProductId').value         = id;
    document.getElementById('stockProductName').textContent = name;
    document.getElementById('stockQuantity').value          = 1;
    document.getElementById('stockReason').value            = '';
    // Pre-select default warehouse
    const sel = document.getElementById('stockWarehouseId');
    sel.innerHTML = `<option value="">${LOCALE === 'ar' ? 'المخزن الافتراضي' : 'Default warehouse'}</option>` + warehouseOpts;
    new bootstrap.Modal(document.getElementById('addStockModal')).show();
}

async function submitAddStock() {
    const id          = document.getElementById('stockProductId').value;
    const warehouseId = document.getElementById('stockWarehouseId').value;
    const payload = {
        quantity: document.getElementById('stockQuantity').value,
        reason:   document.getElementById('stockReason').value,
    };
    if (warehouseId) payload.warehouse_id = parseInt(warehouseId);
    const res = await apiCall(`/api/products/${id}/add-stock`, 'POST', payload);
    if (res.success) {
        showToast('{{ __("pos.stock_added") }}');
        bootstrap.Modal.getInstance(document.getElementById('addStockModal')).hide();
        loadProducts();
    } else {
        showToast(res.message, 'danger');
    }
}

async function deleteProduct(id) {
    if (!confirm('{{ __("pos.confirm_delete") }}')) return;
    const res = await apiCall(`/api/products/${id}`, 'DELETE');
    if (res.success) { showToast('{{ __("pos.success") }}'); loadProducts(); }
    else showToast(res.message, 'danger');
}

// Reset modal on open (only when triggered by the Add button, not editProduct())
document.getElementById('addProductModal').addEventListener('show.bs.modal', function(e) {
    if (!e.relatedTarget) return;
    document.getElementById('productId').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productUnitId').value = '';
    document.getElementById('productQuantity').disabled = false;
    document.getElementById('productWarehouseRow').classList.remove('d-none');
    document.getElementById('productModalTitle').textContent = '{{ __("pos.add_product") }}';
});

// ── Units ─────────────────────────────────────────────────────────────────────

async function loadUnits() {
    const res = await apiCall('{{ route("units.all") }}');
    allUnits  = res.units ?? [];
    unitOpts  = allUnits.map(u =>
        `<option value="${u.id}">${u.name}${u.abbreviation ? ' (' + u.abbreviation + ')' : ''}</option>`
    ).join('');
    document.getElementById('productUnitId').innerHTML =
        `<option value="">{{ __('pos.no_unit') }}</option>` + unitOpts;
    renderUnits();
}

function renderUnits() {
    const tbody = document.getElementById('unitsBody');
    if (!tbody) return;
    tbody.innerHTML = allUnits.length
        ? allUnits.map((u, i) => `
            <tr>
                <td>${i + 1}</td>
                <td class="fw-semibold">${u.name}</td>
                <td><span class="badge bg-secondary">${u.abbreviation || '-'}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-primary" data-unit-action="edit" data-idx="${i}"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-danger" data-unit-action="delete" data-idx="${i}"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('')
        : `<tr><td colspan="4" class="text-center text-muted py-3">{{ __('pos.no_data') }}</td></tr>`;
}

async function saveUnit() {
    const id   = document.getElementById('unitId').value;
    const data = {
        name:         document.getElementById('unitName').value.trim(),
        abbreviation: document.getElementById('unitAbbreviation').value.trim(),
    };
    if (!data.name) { showToast('{{ __("pos.unit_name") }} {{ __("pos.required") ?? "required" }}', 'error'); return; }

    const url    = id ? `/api/units/${id}` : '{{ route("units.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res    = await apiCall(url, method, data);

    if (res.success) {
        showToast('{{ __("pos.success") }}');
        document.getElementById('unitId').value = '';
        document.getElementById('unitName').value = '';
        document.getElementById('unitAbbreviation').value = '';
        await loadUnits();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'error');
    }
}

async function deleteUnit(id) {
    if (!confirm('{{ __("pos.confirm_delete") }}')) return;
    const res = await apiCall(`/api/units/${id}`, 'DELETE');
    if (res.success) { showToast('{{ __("pos.success") }}'); loadUnits(); }
    else showToast(res.message, 'error');
}

document.getElementById('unitsModal').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-unit-action]');
    if (!btn) return;
    const u = allUnits[parseInt(btn.dataset.idx)];
    if (!u) return;
    if (btn.dataset.unitAction === 'edit') {
        document.getElementById('unitId').value = u.id;
        document.getElementById('unitName').value = u.name;
        document.getElementById('unitAbbreviation').value = u.abbreviation || '';
    } else if (btn.dataset.unitAction === 'delete') {
        deleteUnit(u.id);
    }
});

document.getElementById('unitsModal').addEventListener('show.bs.modal', loadUnits);

// ─────────────────────────────────────────────────────────────────────────────

loadWarehouses();
loadUnits();
loadProducts();

document.getElementById('productsBody').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const p = renderedProducts[parseInt(btn.dataset.idx)];
    if (!p) return;
    if (btn.dataset.action === 'barcode')    showBarcode(p.id, p.name||'', p.barcode||'', p.price);
    else if (btn.dataset.action === 'add-stock') showAddStock(p.id, p.name||'');
    else if (btn.dataset.action === 'edit')   editProduct(p);
    else if (btn.dataset.action === 'delete') deleteProduct(p.id);
});

// ─── BARCODE GENERATOR ────────────────────────────────────────────────────────
let _barcodeProductId = null;
let _currentBarcodeValue = '';

async function loadJsBarcode() {
    if (window.JsBarcode) return;
    await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js';
        s.onload = resolve; s.onerror = reject;
        document.head.appendChild(s);
    });
}

async function showBarcode(id, name, barcode, price) {
    _barcodeProductId = id;
    _currentBarcodeValue = barcode;
    document.getElementById('barcodeProductName').textContent = name;
    document.getElementById('barcodeProductPrice').textContent = price ? formatCurrency(price) : '';

    await loadJsBarcode();

    if (barcode) {
        document.getElementById('barcodeGenerateSection').classList.add('d-none');
        renderBarcode(barcode);
    } else {
        const cv = document.getElementById('barcodeCanvas');
        cv.getContext('2d').clearRect(0, 0, cv.width, cv.height);
        document.getElementById('barcodeValue').textContent = '';
        document.getElementById('barcodeGenerateSection').classList.remove('d-none');
    }

    new bootstrap.Modal(document.getElementById('barcodeModal')).show();
}

function renderBarcode(value) {
    try {
        JsBarcode('#barcodeCanvas', value, {
            format: 'CODE128',
            width: 2,
            height: 80,
            displayValue: true,
            fontSize: 14,
            margin: 10,
            background: '#ffffff',
            lineColor: '#000000',
        });
        document.getElementById('barcodeValue').textContent = value;
        _currentBarcodeValue = value;
    } catch(e) {
        document.getElementById('barcodeValue').textContent = '{{ app()->getLocale() === "ar" ? "باركود غير صالح" : "Invalid barcode" }}';
    }
}

async function generateBarcode() {
    // EAN13-style: timestamp-based unique code
    const code = String(Date.now()).slice(-12).padStart(12, '0');
    // Save barcode to product via API
    const res = await apiCall(`/api/products/${_barcodeProductId}`, 'PUT', { barcode: code });
    if (res.success) {
        document.getElementById('barcodeGenerateSection').classList.add('d-none');
        renderBarcode(code);
        loadProducts();
    }
}

function printBarcode() {
    const name  = document.getElementById('barcodeProductName').textContent;
    const price = document.getElementById('barcodeProductPrice').textContent;
    const img   = document.getElementById('barcodeCanvas').toDataURL('image/png');

    const win = window.open('', '_blank', 'width=400,height=300');
    win.document.write(`<!DOCTYPE html><html><head><title>Barcode</title>
    <style>
        body { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; margin:0; font-family:sans-serif; }
        .label { text-align:center; padding:16px; border:1px solid #ddd; border-radius:8px; }
        .prod-name { font-weight:bold; font-size:14px; margin-bottom:4px; }
        .prod-price { color:#16a34a; font-size:13px; margin-bottom:8px; }
    </style></head><body>
    <div class="label">
        <div class="prod-name">${name}</div>
        <div class="prod-price">${price}</div>
        <img src="${img}" style="max-width:260px;">
    </div>
    <script>window.onload=()=>{window.print();window.close();}<\/script>
    </body></html>`);
    win.document.close();
}

function downloadBarcode() {
    const a = document.createElement('a');
    a.href = document.getElementById('barcodeCanvas').toDataURL('image/png');
    a.download = `barcode-${_currentBarcodeValue || 'product'}.png`;
    a.click();
}

function formatCurrency(v) {
    return new Intl.NumberFormat('{{ app()->getLocale() }}', { minimumFractionDigits: 2 }).format(v);
}

// ─── BARCODE SCANNER ─────────────────────────────────────────────────────────
// scannerTarget: 'standalone' → lookup product  |  'field' → fill an input field
let _scannerTarget  = 'standalone';
let _scannerFieldId = null;
let _html5QrCode    = null;

async function _loadHtml5QrCode() {
    if (window.Html5Qrcode) return;
    await new Promise((resolve, reject) => {
        const s    = document.createElement('script');
        s.src      = 'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js';
        s.onload   = resolve;
        s.onerror  = reject;
        document.head.appendChild(s);
    });
}

// Entry point — called by data-fn="openBarcodeScanner" [data-args='["field","productBarcode"]']
async function openBarcodeScanner(target, fieldId) {
    _scannerTarget  = target  || 'standalone';
    _scannerFieldId = fieldId || null;

    // Reset UI
    const resultEl = document.getElementById('barcodeScanResult');
    resultEl.classList.add('d-none');
    resultEl.innerHTML = '';
    document.getElementById('manualBarcodeInput').value = '';

    // Show modal first, then start camera (needs DOM visible for dimensions)
    const modal = new bootstrap.Modal(document.getElementById('barcodeScannerModal'));
    modal.show();
}

// Start camera once modal is fully visible
document.getElementById('barcodeScannerModal').addEventListener('shown.bs.modal', async function() {
    const readerEl = document.getElementById('barcodeScannerReader');
    readerEl.innerHTML = ''; // clear any previous render

    await _loadHtml5QrCode().catch(() => {
        readerEl.innerHTML = `<div class="text-center py-4 text-danger small p-3">
            <i class="fas fa-exclamation-triangle fa-2x mb-2 d-block"></i>
            ${LOCALE === 'ar' ? 'فشل تحميل مكتبة الماسح.' : 'Failed to load scanner library.'}
        </div>`;
        return;
    });

    _html5QrCode = new Html5Qrcode('barcodeScannerReader');
    _html5QrCode.start(
        { facingMode: 'environment' },           // rear camera on mobile
        { fps: 10, qrbox: { width: 280, height: 120 } },  // wide box for 1-D barcodes
        _onScanSuccess,
        null                                     // ignore per-frame decode errors
    ).catch(() => {
        readerEl.innerHTML = `<div class="text-center py-4 text-muted p-3">
            <i class="fas fa-camera-slash fa-2x mb-2 d-block"></i>
            <span class="small">${LOCALE === 'ar' ? 'لا يمكن الوصول للكاميرا. استخدم الإدخال اليدوي أدناه.' : 'Camera unavailable. Use manual input below.'}</span>
        </div>`;
    });
});

// Stop camera when modal closes
document.getElementById('barcodeScannerModal').addEventListener('hide.bs.modal', function() {
    if (_html5QrCode) {
        _html5QrCode.stop().catch(() => {});
        _html5QrCode = null;
    }
});

// Physical-scanner / manual input: listen for Enter key
document.getElementById('manualBarcodeInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); processManualBarcode(); }
});

// "Search" button for manual input
async function processManualBarcode() {
    const barcode = document.getElementById('manualBarcodeInput').value.trim();
    if (!barcode) return;
    if (_html5QrCode) { _html5QrCode.stop().catch(() => {}); _html5QrCode = null; }
    await _handleScannedBarcode(barcode);
}

// Called by html5-qrcode on successful frame decode
async function _onScanSuccess(barcode) {
    if (_html5QrCode) { _html5QrCode.stop().catch(() => {}); _html5QrCode = null; }
    await _handleScannedBarcode(barcode);
}

// Core logic: fill-field mode OR standalone lookup mode
async function _handleScannedBarcode(barcode) {
    // ── Field-fill mode: just fill the target input and close ────────────────
    if (_scannerTarget === 'field' && _scannerFieldId) {
        const targetInput = document.getElementById(_scannerFieldId);
        if (targetInput) targetInput.value = barcode;
        bootstrap.Modal.getInstance(document.getElementById('barcodeScannerModal')).hide();
        showToast(
            (LOCALE === 'ar' ? 'تم مسح الباركود: ' : 'Barcode scanned: ') + barcode,
            'success'
        );
        return;
    }

    // ── Standalone mode: lookup barcode in the system ────────────────────────
    const resultEl = document.getElementById('barcodeScanResult');
    resultEl.innerHTML = `<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>`;
    resultEl.classList.remove('d-none');

    const res = await apiCall(
        '{{ route('products.by-barcode') }}?barcode=' + encodeURIComponent(barcode)
    ).catch(() => null);

    if (!res) {
        resultEl.innerHTML = `<div class="alert alert-danger rounded-0 rounded-bottom mb-0">
            <i class="fas fa-exclamation-circle me-1"></i>
            ${LOCALE === 'ar' ? 'خطأ في الاتصال بالخادم.' : 'Server connection error.'}
        </div>`;
        return;
    }

    if (res.found && res.product) {
        // ── Product EXISTS ──────────────────────────────────────────────────
        const p = res.product;
        resultEl.innerHTML = `
            <div class="alert alert-success rounded-0 rounded-bottom mb-0">
                <div class="fw-bold mb-1">
                    <i class="fas fa-check-circle me-1"></i>
                    ${LOCALE === 'ar' ? 'المنتج موجود:' : 'Product found:'}
                </div>
                <div class="mb-1 fw-semibold">${_esc(p.name)}</div>
                <div class="text-muted small mb-3">
                    <code class="me-2">${_esc(p.barcode || barcode)}</code>
                    <span class="text-success">${formatCurrency(p.price)}</span>
                    &nbsp;·&nbsp;
                    ${LOCALE === 'ar' ? 'المخزون: ' : 'Stock: '}
                    <strong class="${p.quantity === 0 ? 'text-danger' : p.low_stock ? 'text-warning' : 'text-success'}">${p.quantity}</strong>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-primary" data-scan-action="edit" data-product-id="${p.id}">
                        <i class="fas fa-edit me-1"></i>${LOCALE === 'ar' ? 'تعديل' : 'Edit'}
                    </button>
                    <button class="btn btn-sm btn-success" data-scan-action="add-stock"
                        data-product-id="${p.id}" data-product-name="${_esc(p.name)}">
                        <i class="fas fa-plus me-1"></i>${LOCALE === 'ar' ? 'إضافة مخزون' : 'Add Stock'}
                    </button>
                </div>
            </div>`;
    } else {
        // ── Product NOT FOUND locally ───────────────────────────────────────
        const ext      = res.external;           // {name, brand} or null
        const extName  = ext?.name  || '';
        const extBrand = ext?.brand || '';

        // Build "found in global DB" info block
        const extBlock = extName
            ? `<div class="d-flex align-items-start gap-2 p-2 mb-3 rounded bg-white bg-opacity-50">
                   <span class="badge bg-info text-dark flex-shrink-0 mt-1">
                       <i class="fas fa-globe me-1"></i>${LOCALE === 'ar' ? 'قاعدة عالمية' : 'Global DB'}
                   </span>
                   <div>
                       <div class="fw-semibold">${_esc(extName)}</div>
                       ${extBrand ? `<div class="text-muted small">${_esc(extBrand)}</div>` : ''}
                   </div>
               </div>`
            : `<p class="text-muted small mb-3">
                   ${LOCALE === 'ar' ? 'لم يُعثر على اسم المنتج في قواعد البيانات العالمية.' : 'Product name not found in global databases.'}
               </p>`;

        resultEl.innerHTML = `
            <div class="alert alert-warning rounded-0 rounded-bottom mb-0">
                <div class="fw-bold mb-2">
                    <i class="fas fa-question-circle me-1"></i>
                    ${LOCALE === 'ar' ? 'الباركود غير موجود في النظام' : 'Barcode not found in system'}
                    &nbsp;<code class="fw-normal fs-6">${_esc(barcode)}</code>
                </div>
                ${extBlock}
                <button class="btn btn-sm btn-primary"
                    data-scan-action="add-new"
                    data-barcode="${_esc(barcode)}"
                    data-prefill-name="${_esc(extName)}">
                    <i class="fas fa-plus me-1"></i>${LOCALE === 'ar' ? 'إضافة منتج جديد' : 'Add New Product'}
                </button>
            </div>`;
    }
}

// Event delegation for scan-result action buttons (CSP-safe — no onclick attrs)
document.getElementById('barcodeScanResult').addEventListener('click', function(e) {
    const btn = e.target.closest('[data-scan-action]');
    if (!btn) return;
    const action = btn.dataset.scanAction;
    const modal  = bootstrap.Modal.getInstance(document.getElementById('barcodeScannerModal'));

    if (action === 'edit') {
        modal.hide();
        const p = allProducts.find(x => x.id === parseInt(btn.dataset.productId));
        if (p) editProduct(p);

    } else if (action === 'add-stock') {
        modal.hide();
        showAddStock(parseInt(btn.dataset.productId), btn.dataset.productName);

    } else if (action === 'add-new') {
        modal.hide();
        const barcode      = btn.dataset.barcode;
        const prefillName  = btn.dataset.prefillName  || '';
        const prefillPrice = btn.dataset.prefillPrice || '';

        // Pre-fill barcode + name + price (from external DB when available)
        document.getElementById('productId').value       = '';
        document.getElementById('productName').value     = prefillName;
        document.getElementById('productBarcode').value  = barcode;
        document.getElementById('productPrice').value    = prefillPrice;
        document.getElementById('productCategory').value = '';
        document.getElementById('productQuantity').disabled = false;
        document.getElementById('productWarehouseRow').classList.remove('d-none');
        document.getElementById('productModalTitle').textContent =
            LOCALE === 'ar' ? '{{ __("pos.add_product") }}' : 'Add Product';

        // Decide which field to focus:
        //  • name pre-filled but no price → focus price
        //  • name pre-filled and price pre-filled → focus price (still needs local adjustment)
        //  • nothing pre-filled → focus name
        setTimeout(() => {
            new bootstrap.Modal(document.getElementById('addProductModal')).show();
            setTimeout(() => {
                document.getElementById(prefillName ? 'productPrice' : 'productName').focus();
            }, 300);
        }, 300);
    }
});

// Helper: HTML-escape for dynamic content
function _esc(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
// ─────────────────────────────────────────────────────────────────────────────
</script>

{{-- ── Product Export Script ─────────────────────────────────────────── --}}
<script @nonce>
(function () {
  const IS_AR      = {{ app()->getLocale() === 'ar' ? 'true' : 'false' }};
  const exportBase = '{{ route("products.export") }}';
  const expModal   = document.getElementById('exportProductsModal');
  const btnDl      = document.getElementById('btnExportDownload');

  function buildExportUrl() {
    const params = new URLSearchParams();
    const cat    = document.getElementById('exportCategory').value;
    const stock  = document.getElementById('exportStock').value;
    const active = document.getElementById('exportActive').value;
    const fmt    = document.getElementById('exportFormat').value;
    if (cat)         params.set('category', cat);
    if (stock)       params.set('stock',    stock);
    if (active !== '') params.set('active', active);
    params.set('format', fmt);
    return exportBase + (params.toString() ? '?' + params.toString() : '');
  }

  // Populate category dropdown from already-loaded products when modal opens
  expModal.addEventListener('show.bs.modal', function () {
    const sel  = document.getElementById('exportCategory');
    const cats = [...new Set(allProducts.map(p => p.category).filter(Boolean))].sort();
    sel.innerHTML = `<option value="">${IS_AR ? 'جميع الفئات' : 'All Categories'}</option>` +
      cats.map(c => `<option value="${c}">${c}</option>`).join('');
    btnDl.href = buildExportUrl();
  });

  // Live-update href as filters change
  ['exportCategory', 'exportStock', 'exportActive', 'exportFormat'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
      btnDl.href = buildExportUrl();
    });
  });

  // Auto-close modal after clicking download
  btnDl.addEventListener('click', function () {
    setTimeout(() => {
      const bsModal = bootstrap.Modal.getInstance(expModal);
      if (bsModal) bsModal.hide();
    }, 700);
  });
})();
</script>

{{-- ── Product Import Script ─────────────────────────────────────────── --}}
<script @nonce>
(function () {
  const IS_AR      = {{ app()->getLocale() === 'ar' ? 'true' : 'false' }};
  const CSRF       = document.querySelector('meta[name="csrf-token"]').content;
  const importUrl  = '{{ route("products.import") }}';

  const btnRun     = document.getElementById('btnRunImport');
  const fileInput  = document.getElementById('importFile');
  const progress   = document.getElementById('importProgress');
  const resultBox  = document.getElementById('importResult');

  // Reset UI whenever the modal opens
  document.getElementById('importProductsModal').addEventListener('show.bs.modal', () => {
    fileInput.value = '';
    progress.classList.add('d-none');
    resultBox.classList.add('d-none');
    resultBox.innerHTML = '';
  });

  btnRun.addEventListener('click', async () => {
    const file = fileInput.files[0];
    if (!file) {
      Swal.fire({
        icon: 'warning',
        title: IS_AR ? 'لم يتم اختيار ملف' : 'No file selected',
        text:  IS_AR ? 'الرجاء اختيار ملف Excel أو CSV أولاً.' : 'Please choose an Excel or CSV file first.',
      });
      return;
    }

    // Show progress
    btnRun.disabled = true;
    progress.classList.remove('d-none');
    resultBox.classList.add('d-none');

    const form = new FormData();
    form.append('file', file);
    form.append('_token', CSRF);

    try {
      const res  = await fetch(importUrl, { method: 'POST', body: form });
      const data = await res.json();

      progress.classList.add('d-none');
      resultBox.classList.remove('d-none');

      if (data.success) {
        // ── Success result ──────────────────────────────────────────────────
        let html = `
          <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <strong>${IS_AR ? 'تم الاستيراد بنجاح!' : 'Import completed!'}</strong>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-4">
              <div class="card border-0 bg-success bg-opacity-10 text-center p-3">
                <div class="fs-2 fw-bold text-success">${data.imported}</div>
                <div class="small">${IS_AR ? 'منتج جديد' : 'New products'}</div>
              </div>
            </div>
            <div class="col-4">
              <div class="card border-0 bg-primary bg-opacity-10 text-center p-3">
                <div class="fs-2 fw-bold text-primary">${data.updated}</div>
                <div class="small">${IS_AR ? 'منتج محدَّث' : 'Updated'}</div>
              </div>
            </div>
            <div class="col-4">
              <div class="card border-0 bg-${data.errors.length ? 'danger' : 'secondary'} bg-opacity-10 text-center p-3">
                <div class="fs-2 fw-bold text-${data.errors.length ? 'danger' : 'secondary'}">${data.errors.length}</div>
                <div class="small">${IS_AR ? 'أخطاء' : 'Errors'}</div>
              </div>
            </div>
          </div>`;

        if (data.errors.length) {
          html += `
            <div class="alert alert-warning mb-0">
              <strong><i class="fas fa-exclamation-triangle me-1"></i>${IS_AR ? 'تفاصيل الأخطاء:' : 'Error details:'}</strong>
              <ul class="mb-0 mt-2 ps-3">
                ${data.errors.map(e => `<li>${IS_AR ? 'صف' : 'Row'} ${e.row}: ${escHtml(e.error)}</li>`).join('')}
              </ul>
            </div>`;
        }

        resultBox.innerHTML = html;

        // Refresh the products table
        if (typeof loadProducts === 'function') loadProducts();

      } else {
        // ── Validation / server error ───────────────────────────────────────
        let errHtml = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>${escHtml(data.message ?? '')}</strong></div>`;
        if (data.errors?.length) {
          errHtml += `<ul class="list-group">
            ${data.errors.map(e => `<li class="list-group-item list-group-item-danger py-1">
              <strong>${IS_AR ? 'صف' : 'Row'} ${e.row}:</strong> ${escHtml(e.error)}
            </li>`).join('')}
          </ul>`;
        }
        resultBox.innerHTML = errHtml;
      }

    } catch (err) {
      progress.classList.add('d-none');
      resultBox.classList.remove('d-none');
      resultBox.innerHTML = `<div class="alert alert-danger"><i class="fas fa-wifi me-2"></i>${IS_AR ? 'تعذّر الاتصال بالخادم.' : 'Could not reach the server.'}</div>`;
    } finally {
      btnRun.disabled = false;
    }
  });

  function escHtml(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
</script>
@endpush
