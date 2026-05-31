@extends('layouts.app')
@section('title', __('pos.expenses'))
@section('page-title', __('pos.expenses'))

@push('styles')
<style @nonce>
    .expense-row td { vertical-align: middle; }
    .pm-badge { font-size:.7rem; padding:.3em .6em; }
</style>
@endpush

@section('content')
<div class="container-fluid py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-receipt me-2 text-warning"></i>{{ __('pos.expenses') }}
        </h5>
        @permission('view_pos')
        <button class="btn btn-warning btn-sm text-white" data-fn="openExpenseModal" data-args='[null]'>
            <i class="fas fa-plus me-1"></i>{{ __('pos.new_expense') }}
        </button>
        @endpermission
    </div>

    {{-- Filters --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-sm-3 col-6">
                    <label class="form-label form-label-sm mb-1">{{ __('pos.expense_category') }}</label>
                    <select class="form-select form-select-sm" id="filterCategory" data-on-change="applyFilters">
                        <option value="">{{ app()->getLocale() === 'ar' ? 'الكل' : 'All' }}</option>
                    </select>
                </div>
                <div class="col-sm-3 col-6">
                    <label class="form-label form-label-sm mb-1">{{ app()->getLocale() === 'ar' ? 'طريقة الدفع' : 'Payment Method' }}</label>
                    <select class="form-select form-select-sm" id="filterPayment" data-on-change="applyFilters">
                        <option value="">{{ app()->getLocale() === 'ar' ? 'الكل' : 'All' }}</option>
                        <option value="cash">{{ app()->getLocale() === 'ar' ? 'نقداً' : 'Cash' }}</option>
                        <option value="card">{{ app()->getLocale() === 'ar' ? 'بطاقة' : 'Card' }}</option>
                        <option value="transfer">{{ app()->getLocale() === 'ar' ? 'تحويل' : 'Transfer' }}</option>
                        <option value="wallet">{{ app()->getLocale() === 'ar' ? 'محفظة' : 'Wallet' }}</option>
                    </select>
                </div>
                <div class="col-sm-2 col-6">
                    <label class="form-label form-label-sm mb-1">{{ app()->getLocale() === 'ar' ? 'من' : 'From' }}</label>
                    <input type="date" class="form-control form-control-sm" id="filterFrom" data-on-change="applyFilters">
                </div>
                <div class="col-sm-2 col-6">
                    <label class="form-label form-label-sm mb-1">{{ app()->getLocale() === 'ar' ? 'إلى' : 'To' }}</label>
                    <input type="date" class="form-control form-control-sm" id="filterTo" data-on-change="applyFilters">
                </div>
                <div class="col-sm-2 col-12">
                    <button class="btn btn-outline-secondary btn-sm w-100" data-fn="resetFilters">
                        <i class="fas fa-times me-1"></i>{{ app()->getLocale() === 'ar' ? 'مسح' : 'Reset' }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3 mb-3" id="summaryCards" class="u-hidden-imp"></div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('pos.expense_number') }}</th>
                            <th>{{ __('pos.expense_title') }}</th>
                            <th>{{ __('pos.expense_category') }}</th>
                            <th>{{ __('pos.expense_date') }}</th>
                            <th class="text-end">{{ app()->getLocale() === 'ar' ? 'المبلغ' : 'Amount' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'الدفع' : 'Payment' }}</th>
                            <th>{{ app()->getLocale() === 'ar' ? 'بواسطة' : 'By' }}</th>
                            <th class="text-center">{{ app()->getLocale() === 'ar' ? 'إجراء' : 'Action' }}</th>
                        </tr>
                    </thead>
                    <tbody id="expensesTbody">
                        <tr><td colspan="8" class="text-center py-4">
                            <i class="fas fa-spinner fa-spin me-1"></i>
                            {{ app()->getLocale() === 'ar' ? 'جاري التحميل...' : 'Loading...' }}
                        </td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center py-2">
            <span id="expensesTotal" class="fw-bold text-warning"></span>
            <div id="expensesPagination" class="d-flex gap-1"></div>
        </div>
    </div>
</div>

{{-- Expense Modal --}}
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseModalTitle">{{ __('pos.new_expense') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="expenseId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('pos.expense_title') }} <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="expenseTitle" maxlength="255">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'المبلغ' : 'Amount' }} <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="expenseAmount" min="0.01" step="0.01">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ __('pos.expense_date') }} <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="expenseDate">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ __('pos.expense_category') }}</label>
                        <select class="form-select" id="expenseCategory">
                            <option value="">{{ app()->getLocale() === 'ar' ? 'بدون فئة' : 'No category' }}</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'طريقة الدفع' : 'Payment Method' }}</label>
                        <select class="form-select" id="expensePayment">
                            <option value="cash">{{ app()->getLocale() === 'ar' ? 'نقداً' : 'Cash' }}</option>
                            <option value="card">{{ app()->getLocale() === 'ar' ? 'بطاقة' : 'Card' }}</option>
                            <option value="transfer">{{ app()->getLocale() === 'ar' ? 'تحويل بنكي' : 'Bank Transfer' }}</option>
                            <option value="wallet">{{ app()->getLocale() === 'ar' ? 'محفظة إلكترونية' : 'E-Wallet' }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'مرجع / رقم إيصال' : 'Reference / Receipt No.' }}</label>
                        <input type="text" class="form-control" id="expenseRef" maxlength="100">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ app()->getLocale() === 'ar' ? 'ملاحظات' : 'Notes' }}</label>
                        <textarea class="form-control" id="expenseNotes" rows="2" maxlength="1000"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                    {{ app()->getLocale() === 'ar' ? 'إلغاء' : 'Cancel' }}
                </button>
                <button type="button" class="btn btn-warning btn-sm text-white" data-fn="saveExpense" id="saveExpenseBtn">
                    <i class="fas fa-save me-1"></i>{{ app()->getLocale() === 'ar' ? 'حفظ' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';
let categories = [];
let currentPage = 1;

const pmLabel = { cash: isAr?'نقداً':'Cash', card: isAr?'بطاقة':'Card', transfer: isAr?'تحويل':'Transfer', wallet: isAr?'محفظة':'Wallet' };
const pmClass  = { cash:'success', card:'primary', transfer:'info', wallet:'warning' };

async function loadCategories() {
    const res = await apiCall('{{ route("expenses.categories") }}');
    if (!res.success) return;
    categories = res.categories;
    const selFilter = document.getElementById('filterCategory');
    const selForm   = document.getElementById('expenseCategory');
    categories.forEach(c => {
        const opt1 = new Option(c.name, c.id);
        const opt2 = new Option(c.name, c.id);
        selFilter.appendChild(opt1);
        selForm.appendChild(opt2);
    });
}

async function loadExpenses(page = 1) {
    currentPage = page;
    const tbody = document.getElementById('expensesTbody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin me-1"></i>${isAr?'جاري التحميل...':'Loading...'}</td></tr>`;

    const params = new URLSearchParams();
    const cat  = document.getElementById('filterCategory').value;
    const pm   = document.getElementById('filterPayment').value;
    const from = document.getElementById('filterFrom').value;
    const to   = document.getElementById('filterTo').value;
    if (cat)  params.set('category_id', cat);
    if (pm)   params.set('payment_method', pm);
    if (from) params.set('date_from', from);
    if (to)   params.set('date_to', to);
    params.set('page', page);
    params.set('per_page', 20);

    const res = await apiCall('{{ route("expenses.all") }}?' + params.toString());
    if (!res.success) { tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${res.message}</td></tr>`; return; }

    const data = res.expenses;
    if (!data || !data.data.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-muted">${isAr?'لا توجد مصروفات':'No expenses found'}</td></tr>`;
        document.getElementById('expensesTotal').textContent = '';
        document.getElementById('expensesPagination').innerHTML = '';
        return;
    }

    let total = 0;
    tbody.innerHTML = data.data.map(e => {
        total += parseFloat(e.amount);
        return `<tr class="expense-row">
            <td><span class="badge bg-secondary">${e.expense_number}</span></td>
            <td>${escHtml(e.title)}</td>
            <td>${e.category ? escHtml(e.category.name) : '<span class="text-muted">—</span>'}</td>
            <td>${formatDate(e.expense_date)}</td>
            <td class="text-end fw-semibold">${formatCurrency(e.amount)}</td>
            <td><span class="badge bg-${pmClass[e.payment_method]||'secondary'} pm-badge">${pmLabel[e.payment_method]||e.payment_method}</span></td>
            <td class="small text-muted">${escHtml(e.created_by_name||'')}</td>
            <td class="text-center">
                <button class="btn btn-xs btn-outline-primary btn-sm me-1" data-fn="openExpenseModal" data-args='[${JSON.stringify(e)}]' title="${isAr?'تعديل':'Edit'}">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-xs btn-outline-danger btn-sm" data-fn="deleteExpense" data-args='[${e.id},"${escHtml(e.expense_number)}"]' title="${isAr?'حذف':'Delete'}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    }).join('');

    document.getElementById('expensesTotal').textContent = (isAr ? 'الإجمالي: ' : 'Total: ') + formatCurrency(total);
    renderPagination(data);
}

function renderPagination(data) {
    const el = document.getElementById('expensesPagination');
    if (data.last_page <= 1) { el.innerHTML = ''; return; }
    let html = '';
    if (data.current_page > 1)
        html += `<button class="btn btn-sm btn-outline-secondary" data-fn="loadExpenses" data-args="[${data.current_page-1}]">‹</button>`;
    html += `<span class="btn btn-sm btn-secondary disabled">${data.current_page}/${data.last_page}</span>`;
    if (data.current_page < data.last_page)
        html += `<button class="btn btn-sm btn-outline-secondary" data-fn="loadExpenses" data-args="[${data.current_page+1}]">›</button>`;
    el.innerHTML = html;
}

window.applyFilters = function() { loadExpenses(1); };
window.resetFilters = function() {
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterPayment').value  = '';
    document.getElementById('filterFrom').value     = '';
    document.getElementById('filterTo').value       = '';
    loadExpenses(1);
};

window.openExpenseModal = function(expense) {
    document.getElementById('expenseId').value      = expense?.id ?? '';
    document.getElementById('expenseTitle').value   = expense?.title ?? '';
    document.getElementById('expenseAmount').value  = expense?.amount ?? '';
    document.getElementById('expenseDate').value    = expense?.expense_date ? expense.expense_date.substring(0,10) : new Date().toISOString().substring(0,10);
    document.getElementById('expenseCategory').value= expense?.category_id ?? '';
    document.getElementById('expensePayment').value = expense?.payment_method ?? 'cash';
    document.getElementById('expenseRef').value     = expense?.reference ?? '';
    document.getElementById('expenseNotes').value   = expense?.notes ?? '';
    document.getElementById('expenseModalTitle').textContent = expense ? (isAr?'تعديل مصروف':'Edit Expense') : '{{ __("pos.new_expense") }}';
    new bootstrap.Modal(document.getElementById('expenseModal')).show();
};

window.saveExpense = async function() {
    const id    = document.getElementById('expenseId').value;
    const title = document.getElementById('expenseTitle').value.trim();
    const amount= document.getElementById('expenseAmount').value;
    const date  = document.getElementById('expenseDate').value;
    if (!title || !amount || !date) { showToast(isAr?'يرجى ملء الحقول المطلوبة':'Please fill required fields', 'error'); return; }

    const payload = {
        title,
        amount:         parseFloat(amount),
        expense_date:   date,
        category_id:    document.getElementById('expenseCategory').value || null,
        payment_method: document.getElementById('expensePayment').value,
        reference:      document.getElementById('expenseRef').value || null,
        notes:          document.getElementById('expenseNotes').value || null,
    };

    const btn = document.getElementById('saveExpenseBtn');
    btn.disabled = true;
    const url    = id ? `{{ url('/api/expenses') }}/${id}` : '{{ route("expenses.store") }}';
    const method = id ? 'PUT' : 'POST';
    const res = await apiCall(url, method, payload);
    btn.disabled = false;

    if (!res.success) { showToast(res.message || (isAr?'حدث خطأ':'Error'), 'error'); return; }
    bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
    showToast(isAr ? (id?'تم التعديل':'تم الحفظ') : (id?'Updated':'Saved'));
    loadExpenses(currentPage);
};

window.deleteExpense = async function(id, num) {
    if (!confirm(isAr ? `هل تريد حذف المصروف ${num}؟` : `Delete expense ${num}?`)) return;
    const res = await apiCall(`{{ url('/api/expenses') }}/${id}`, 'DELETE');
    if (!res.success) { showToast(res.message, 'error'); return; }
    showToast(res.message || (isAr?'تم الحذف':'Deleted'));
    loadExpenses(currentPage);
};

function escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

window.loadExpenses = loadExpenses;

document.addEventListener('DOMContentLoaded', function() {
    loadCategories().then(() => loadExpenses(1));
});
</script>
@endpush
