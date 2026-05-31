@extends('layouts.app')

@section('title', app()->getLocale()==='ar' ? 'إدارة الكاش باك' : 'Cashback Management')
@section('page-title', app()->getLocale()==='ar' ? 'إدارة الكاش باك' : 'Cashback Management')

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

{{-- Stats row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success">{{ number_format($totalEarned, 2) }}</div>
                <div class="small text-muted">{{ $isAr ? 'إجمالي المكتسب' : 'Total Earned' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-warning">{{ number_format($totalRedeemed, 2) }}</div>
                <div class="small text-muted">{{ $isAr ? 'إجمالي المستخدم' : 'Total Redeemed' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary">{{ number_format($totalBalance, 2) }}</div>
                <div class="small text-muted">{{ $isAr ? 'رصيد العملاء الكلي' : 'Total Customer Balance' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center h-100 {{ $activeRule ? 'border-success' : 'border-secondary' }}">
            <div class="card-body py-3">
                @if($activeRule)
                    <div class="fs-4 fw-bold text-success">{{ $activeRule->percentage }}%</div>
                    <div class="small text-muted">{{ $isAr ? 'نسبة الكاش باك الحالية' : 'Active Cashback Rate' }}</div>
                @else
                    <div class="fs-4 fw-bold text-secondary">—</div>
                    <div class="small text-muted">{{ $isAr ? 'لا توجد قاعدة نشطة' : 'No Active Rule' }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Left: Rules management --}}
    <div class="col-lg-5">
        {{-- Active rule card --}}
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">
                    <i class="fas fa-star text-warning me-2"></i>
                    {{ $isAr ? 'القاعدة النشطة' : 'Active Rule' }}
                </span>
                <button class="btn btn-sm btn-primary" data-fn="openAddRuleModal">
                    <i class="fas fa-plus me-1"></i>{{ $isAr ? 'قاعدة جديدة' : 'New Rule' }}
                </button>
            </div>
            <div class="card-body">
                @if($activeRule)
                    <table class="table table-sm mb-0">
                        <tr>
                            <th class="text-muted fw-normal small">{{ $isAr ? 'الاسم' : 'Name' }}</th>
                            <td class="fw-semibold">{{ $activeRule->name }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal small">{{ $isAr ? 'النسبة' : 'Percentage' }}</th>
                            <td><span class="badge bg-success">{{ $activeRule->percentage }}%</span></td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal small">{{ $isAr ? 'الحد الأدنى للشراء' : 'Min Purchase' }}</th>
                            <td>{{ number_format($activeRule->min_purchase, 2) }}</td>
                        </tr>
                        <tr>
                            <th class="text-muted fw-normal small">{{ $isAr ? 'الحد الأقصى للكاش باك' : 'Max Cashback' }}</th>
                            <td>{{ $activeRule->max_cashback ? number_format($activeRule->max_cashback, 2) : ($isAr ? 'غير محدود' : 'Unlimited') }}</td>
                        </tr>
                    </table>
                @else
                    <div class="text-center py-3 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2 d-block opacity-50"></i>
                        {{ $isAr ? 'لا توجد قاعدة كاش باك نشطة. أضف قاعدة جديدة لتفعيل الكاش باك.' : 'No active cashback rule. Add a new rule to enable cashback.' }}
                    </div>
                @endif
            </div>
        </div>

        {{-- All rules history --}}
        <div class="card">
            <div class="card-header fw-semibold small">
                <i class="fas fa-history me-2"></i>{{ $isAr ? 'سجل القواعد' : 'Rules History' }}
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ $isAr ? 'الاسم' : 'Name' }}</th>
                            <th>{{ $isAr ? 'النسبة' : '%' }}</th>
                            <th>{{ $isAr ? 'الحالة' : 'Status' }}</th>
                            <th>{{ $isAr ? 'التاريخ' : 'Date' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allRules as $rule)
                            <tr>
                                <td class="fw-semibold small">{{ $rule->name }}</td>
                                <td><span class="badge bg-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->percentage }}%</span></td>
                                <td>
                                    @if($rule->is_active)
                                        <span class="badge bg-success">{{ $isAr ? 'نشط' : 'Active' }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $isAr ? 'غير نشط' : 'Inactive' }}</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $rule->created_at->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">{{ $isAr ? 'لا توجد قواعد' : 'No rules yet' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Recent transactions --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header fw-semibold">
                <i class="fas fa-list me-2"></i>{{ $isAr ? 'أحدث المعاملات' : 'Recent Transactions' }}
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ $isAr ? 'العميل' : 'Customer' }}</th>
                            <th>{{ $isAr ? 'النوع' : 'Type' }}</th>
                            <th>{{ $isAr ? 'المبلغ' : 'Amount' }}</th>
                            <th>{{ $isAr ? 'الرصيد بعد' : 'Balance After' }}</th>
                            <th>{{ $isAr ? 'التاريخ' : 'Date' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentTxns as $txn)
                            <tr>
                                <td class="fw-semibold small">{{ $txn->customer?->name ?? '—' }}</td>
                                <td>
                                    @php
                                        $typeConfig = [
                                            'earned'   => ['success', $isAr ? 'مكتسب' : 'Earned', 'fa-plus'],
                                            'redeemed' => ['warning', $isAr ? 'مستخدم' : 'Redeemed', 'fa-minus'],
                                            'expired'  => ['secondary', $isAr ? 'منتهي' : 'Expired', 'fa-clock'],
                                            'adjusted' => ['info', $isAr ? 'معدّل' : 'Adjusted', 'fa-pen'],
                                        ];
                                        $cfg = $typeConfig[$txn->type] ?? ['secondary', $txn->type, 'fa-circle'];
                                    @endphp
                                    <span class="badge bg-{{ $cfg[0] }}">
                                        <i class="fas {{ $cfg[2] }} me-1"></i>{{ $cfg[1] }}
                                    </span>
                                </td>
                                <td class="fw-semibold text-{{ $txn->type === 'earned' ? 'success' : ($txn->type === 'redeemed' ? 'warning' : 'secondary') }}">
                                    {{ $txn->type === 'earned' ? '+' : '-' }}{{ number_format($txn->amount, 2) }}
                                </td>
                                <td class="small">{{ number_format($txn->balance_after, 2) }}</td>
                                <td class="text-muted small">{{ $txn->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                    {{ $isAr ? 'لا توجد معاملات بعد' : 'No transactions yet' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add Rule Modal --}}
<div class="modal fade" id="addRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star me-2 text-warning"></i>
                    {{ $isAr ? 'إضافة قاعدة كاش باك جديدة' : 'New Cashback Rule' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ $isAr ? 'إضافة قاعدة جديدة ستُلغي القاعدة النشطة الحالية تلقائياً.' : 'Adding a new rule will automatically deactivate the current active rule.' }}
                </p>
                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ $isAr ? 'اسم القاعدة' : 'Rule Name' }}</label>
                    <input type="text" class="form-control" id="ruleName" placeholder="{{ $isAr ? 'مثال: كاش باك صيف 2026' : 'e.g. Summer Cashback 2026' }}">
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">{{ $isAr ? 'النسبة (%)' : 'Percentage (%)' }}</label>
                        <input type="number" class="form-control" id="rulePercentage" min="0.01" max="100" step="0.01" placeholder="5.00">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">{{ $isAr ? 'الحد الأدنى للشراء' : 'Min Purchase' }}</label>
                        <input type="number" class="form-control" id="ruleMinPurchase" min="0" step="0.01" placeholder="0.00">
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label fw-semibold">{{ $isAr ? 'الحد الأقصى للكاش باك (اتركه فارغاً للسماح بلا حد)' : 'Max Cashback (leave blank for unlimited)' }}</label>
                    <input type="number" class="form-control" id="ruleMaxCashback" min="0" step="0.01" placeholder="{{ $isAr ? 'غير محدود' : 'Unlimited' }}">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">{{ $isAr ? 'إلغاء' : 'Cancel' }}</button>
                <button class="btn btn-primary" data-fn="saveNewRule">
                    <i class="fas fa-save me-1"></i>{{ $isAr ? 'حفظ وتفعيل' : 'Save & Activate' }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const isAr = LOCALE === 'ar';

function openAddRuleModal() {
    new bootstrap.Modal(document.getElementById('addRuleModal')).show();
}

async function saveNewRule() {
    const name        = document.getElementById('ruleName').value.trim();
    const percentage  = parseFloat(document.getElementById('rulePercentage').value);
    const minPurchase = parseFloat(document.getElementById('ruleMinPurchase').value) || 0;
    const maxCashback = document.getElementById('ruleMaxCashback').value.trim();

    if (!name || !percentage || percentage <= 0) {
        showToast(isAr ? 'يرجى إدخال الاسم والنسبة' : 'Please enter name and percentage', 'warning');
        return;
    }

    const body = { name, percentage, min_purchase: minPurchase };
    if (maxCashback) body.max_cashback = parseFloat(maxCashback);

    const res = await apiCall('/api/cashback/rules', 'POST', body);
    if (res.rule) {
        showToast(isAr ? 'تم حفظ القاعدة وتفعيلها' : 'Rule saved and activated', 'success');
        bootstrap.Modal.getInstance(document.getElementById('addRuleModal'))?.hide();
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(res.message || (isAr ? 'حدث خطأ' : 'An error occurred'), 'danger');
    }
}
</script>
@endpush
