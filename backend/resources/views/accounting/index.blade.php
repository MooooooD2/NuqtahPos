{{-- FILE: resources/views/accounting/index.blade.php --}}
@extends('layouts.app')
@section('title', __('pos.accounting'))
@section('page-title', __('pos.accounting'))

@section('content')
<ul class="nav nav-tabs mb-4" id="accountingTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#accountsTab">
            <i class="fas fa-sitemap me-1"></i>{{ __('pos.account_name') }}
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#journalTab">
            <i class="fas fa-book me-1"></i>{{ __('pos.journal_entry') }}
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- Chart of Accounts Tab --}}
    <div class="tab-pane fade show active" id="accountsTab">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-sitemap me-2"></i>{{ __('pos.account_code') }} - {{ __('pos.account_name') }}</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal">
                    <i class="fas fa-plus me-1"></i>{{ __('pos.add_account') }}
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>{{ __('pos.account_code') }}</th>
                                <th>{{ __('pos.account_name') }}</th>
                                <th>{{ __('pos.account_type') }}</th>
                                <th>Parent</th>
                                <th>{{ __('pos.total') }}</th>
                                <th>{{ __('pos.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody id="accountsBody">
                            <tr><td colspan="6" class="text-center py-4"><div class="spinner-border"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Journal Entries Tab --}}
    <div class="tab-pane fade" id="journalTab">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-book me-2"></i>{{ __('pos.journal_entry') }}</span>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addJournalModal">
                    <i class="fas fa-plus me-1"></i>{{ __('pos.add_account') }}
                </button>
            </div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="jeStartDate">
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="jeEndDate">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100" data-fn="loadJournalEntries">
                            <i class="fas fa-search"></i> {{ __('pos.filter') }}
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Entry #</th>
                                <th>{{ __('pos.date') }}</th>
                                <th>Description</th>
                                <th>{{ __('pos.debit') }}</th>
                                <th>{{ __('pos.credit') }}</th>
                            </tr>
                        </thead>
                        <tbody id="journalBody">
                            <tr><td colspan="5" class="text-center py-4"><div class="spinner-border"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Add Account Modal --}}
<div class="modal fade" id="addAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pos.add_account') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.account_code') }} *</label>
                    <input type="text" class="form-control" id="newAccountCode">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.account_name') }} *</label>
                    <input type="text" class="form-control" id="newAccountName">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.account_type') }} *</label>
                    <select class="form-select" id="newAccountType">
                        <option value="asset">{{ __('pos.asset') }}</option>
                        <option value="liability">{{ __('pos.liability') }}</option>
                        <option value="equity">{{ __('pos.equity') }}</option>
                        <option value="revenue">{{ __('pos.revenue') }}</option>
                        <option value="expense">{{ __('pos.expense') }}</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Parent Account</label>
                    <select class="form-select" id="newAccountParent">
                        <option value="">-- None / لا يوجد --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('pos.notes') }}</label>
                    <input type="text" class="form-control" id="newAccountDesc">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveAccount">{{ __('pos.save') }}</button>
            </div>
        </div>
    </div>
</div>

{{-- Add Journal Entry Modal --}}
<div class="modal fade" id="addJournalModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('pos.journal_entry') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('pos.date') }} *</label>
                        <input type="date" class="form-control" id="jeDate" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" id="jeDescription">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered" id="jeTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40%">{{ __('pos.account_name') }}</th>
                                <th style="width:25%">{{ __('pos.debit') }}</th>
                                <th style="width:25%">{{ __('pos.credit') }}</th>
                                <th style="width:10%"></th>
                            </tr>
                        </thead>
                        <tbody id="jeLines"></tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">
                                    <button class="btn btn-sm btn-outline-primary w-100" data-fn="addJELine">
                                        <i class="fas fa-plus me-1"></i> Add Line
                                    </button>
                                </td>
                            </tr>
                            <tr class="table-light fw-bold">
                                <td class="text-end">{{ __('pos.total') }}</td>
                                <td id="jeTotalDebit" class="text-success">0.00</td>
                                <td id="jeTotalCredit" class="text-primary">0.00</td>
                                <td id="jeBalance" class="text-center"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
                <button class="btn btn-primary" data-fn="saveJournalEntry">{{ __('pos.save') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
let allAccounts = [];
let jeLineCount = 0;

const typeColors = {
    asset:     'primary',
    liability: 'warning',
    equity:    'info',
    revenue:   'success',
    expense:   'danger',
};

const typeLabels = {
    asset:     '{{ __("pos.asset") }}',
    liability: '{{ __("pos.liability") }}',
    equity:    '{{ __("pos.equity") }}',
    revenue:   '{{ __("pos.revenue") }}',
    expense:   '{{ __("pos.expense") }}',
};

async function loadAccounts() {
    const res  = await apiCall('{{ route("accounts.all") }}');
    allAccounts = res.accounts || [];

    // Populate parent dropdowns
    const parentSel = document.getElementById('newAccountParent');
    parentSel.innerHTML = '<option value="">-- None --</option>' +
        allAccounts.map(a => `<option value="${a.id}">${a.account_code} - ${a.account_name}</option>`).join('');

    document.getElementById('accountsBody').innerHTML = allAccounts.length
        ? allAccounts.map(a => `
            <tr>
                <td><code class="fw-bold">${a.account_code}</code></td>
                <td>${a.parent_id ? '&nbsp;&nbsp;&nbsp;↳' : ''} ${a.account_name}</td>
                <td><span class="badge bg-${typeColors[a.account_type]}">${typeLabels[a.account_type]}</span></td>
                <td class="text-muted small">${a.parent?.account_name || '-'}</td>
                <td class="fw-semibold">${formatCurrency(a.balance)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" data-fn="deleteAccount" data-args='[${a.id}]'>
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`).join('')
        : '<tr><td colspan="6" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

async function saveAccount() {
    const res = await apiCall('{{ route("accounts.store") }}', 'POST', {
        account_code: document.getElementById('newAccountCode').value,
        account_name: document.getElementById('newAccountName').value,
        account_type: document.getElementById('newAccountType').value,
        parent_id:    document.getElementById('newAccountParent').value || null,
        description:  document.getElementById('newAccountDesc').value,
    });
    if (res.success) {
        showToast('{{ __("pos.success") }}');
        bootstrap.Modal.getInstance(document.getElementById('addAccountModal')).hide();
        loadAccounts();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

async function deleteAccount(id) {
    if (!confirm('{{ __("pos.confirm_delete") }}')) return;
    const res = await apiCall(`/api/accounts/${id}`, 'DELETE');
    if (res.success) { showToast('{{ __("pos.success") }}'); loadAccounts(); }
    else showToast(res.message, 'danger');
}

async function loadJournalEntries() {
    const start = document.getElementById('jeStartDate').value;
    const end   = document.getElementById('jeEndDate').value;
    let url     = '{{ route("journal-entries.all") }}';
    if (start) url += `?start_date=${start}`;
    if (end)   url += `${start ? '&' : '?'}end_date=${end}`;

    const res     = await apiCall(url);
    const entries = res.entries?.data || [];

    document.getElementById('journalBody').innerHTML = entries.length
        ? entries.map(e => {
            const totalDebit  = e.lines?.reduce((s, l) => s + l.debit, 0) || 0;
            const totalCredit = e.lines?.reduce((s, l) => s + l.credit, 0) || 0;
            return `<tr>
                <td><span class="badge bg-secondary">${e.entry_number}</span></td>
                <td>${formatDate(e.entry_date)}</td>
                <td>${e.description || '-'}</td>
                <td class="text-success fw-semibold">${formatCurrency(totalDebit)}</td>
                <td class="text-primary fw-semibold">${formatCurrency(totalCredit)}</td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="5" class="text-center text-muted py-4">{{ __("pos.no_data") }}</td></tr>';
}

function addJELine() {
    const idx  = jeLineCount++;
    const opts = allAccounts.map(a => `<option value="${a.id}">${a.account_code} - ${a.account_name}</option>`).join('');
    const tr   = document.createElement('tr');
    tr.id      = `jeLine${idx}`;
    tr.innerHTML = `
        <td><select class="form-select form-select-sm" id="jeAcc${idx}"><option value="">--</option>${opts}</select></td>
        <td><input type="number" class="form-control form-control-sm" id="jeDr${idx}" value="0" step="0.01" data-on-change="updateJETotals"></td>
        <td><input type="number" class="form-control form-control-sm" id="jeCr${idx}" value="0" step="0.01" data-on-change="updateJETotals"></td>
        <td><button class="btn btn-sm btn-outline-danger" data-fn="removeJELine" data-args='[${idx}]'><i class="fas fa-trash"></i></button></td>`;
    document.getElementById('jeLines').appendChild(tr);
}

function removeJELine(idx) {
    document.getElementById(`jeLine${idx}`)?.remove();
    updateJETotals();
}

function updateJETotals() {
    let dr = 0, cr = 0;
    document.querySelectorAll('[id^="jeDr"]').forEach(el => dr += parseFloat(el.value) || 0);
    document.querySelectorAll('[id^="jeCr"]').forEach(el => cr += parseFloat(el.value) || 0);
    document.getElementById('jeTotalDebit').textContent  = formatCurrency(dr);
    document.getElementById('jeTotalCredit').textContent = formatCurrency(cr);
    const bal = document.getElementById('jeBalance');
    bal.textContent = Math.abs(dr - cr) < 0.01
        ? '✅ Balanced'
        : `⚠️ ${formatCurrency(Math.abs(dr - cr))}`;
    bal.className = Math.abs(dr - cr) < 0.01 ? 'text-success' : 'text-danger';
}

async function saveJournalEntry() {
    const lines = [];
    document.querySelectorAll('[id^="jeDr"]').forEach(el => {
        const idx = el.id.replace('jeDr','');
        const acc = document.getElementById(`jeAcc${idx}`)?.value;
        if (!acc) return;
        lines.push({
            account_id: acc,
            debit:      parseFloat(el.value) || 0,
            credit:     parseFloat(document.getElementById(`jeCr${idx}`)?.value) || 0,
        });
    });

    const res = await apiCall('{{ route("journal-entries.store") }}', 'POST', {
        entry_date:  document.getElementById('jeDate').value,
        description: document.getElementById('jeDescription').value,
        lines,
    });

    if (res.success) {
        showToast('{{ __("pos.success") }}');
        bootstrap.Modal.getInstance(document.getElementById('addJournalModal')).hide();
        document.getElementById('jeLines').innerHTML = '';
        jeLineCount = 0;
        loadJournalEntries();
        loadAccounts();
    } else {
        showToast(res.message || '{{ __("pos.error") }}', 'danger');
    }
}

// Init default journal lines
document.getElementById('addJournalModal').addEventListener('show.bs.modal', () => {
    if (document.getElementById('jeLines').children.length === 0) {
        addJELine(); addJELine();
    }
});

loadAccounts();
loadJournalEntries();
</script>
@endpush
