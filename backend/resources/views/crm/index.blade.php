@extends('layouts.app')
@section('title', __('pos.crm_title'))
@section('page-title', '👥 ' . __('pos.crm_title'))

@section('content')
{{-- Stats Row --}}
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center border-primary">
      <div class="card-body">
        <div class="fs-4 fw-bold text-primary">{{ $stats['total_customers'] }}</div>
        <div class="small text-muted">{{ __('pos.total_customers') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-success">
      <div class="card-body">
        <div class="fs-4 fw-bold text-success">{{ $stats['new_this_month'] }}</div>
        <div class="small text-muted">{{ __('pos.new_this_month') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-warning">
      <div class="card-body">
        <div class="fs-4 fw-bold text-warning">{{ $stats['pending_followups'] }}</div>
        <div class="small text-muted">{{ __('pos.pending_followups') }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-info">
      <div class="card-body">
        <div class="fs-5 fw-bold text-info">{{ $stats['by_lifecycle']['loyal'] ?? 0 }}</div>
        <div class="small text-muted">{{ __('pos.loyal_customers') }}</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- Lifecycle Distribution --}}
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header">{{ __('pos.customer_lifecycle') }}</div>
      <div class="card-body">
        @php
          $colors = ['lead'=>'secondary','prospect'=>'info','customer'=>'primary','loyal'=>'success','at_risk'=>'warning','churned'=>'danger'];
          $total  = max(1, array_sum($stats['by_lifecycle']));
          $stageLabels = [
            'lead'     => __('pos.stage_lead'),
            'prospect' => __('pos.stage_prospect'),
            'customer' => __('pos.stage_customer'),
            'loyal'    => __('pos.stage_loyal'),
            'at_risk'  => __('pos.stage_at_risk'),
            'churned'  => __('pos.stage_churned'),
          ];
        @endphp
        @foreach($stats['by_lifecycle'] as $stage => $count)
        <div class="mb-2">
          <div class="d-flex justify-content-between mb-1">
            <span class="badge bg-{{ $colors[$stage] ?? 'secondary' }}">{{ $stageLabels[$stage] ?? ucfirst(str_replace('_',' ',$stage)) }}</span>
            <span class="small fw-bold">{{ $count }}</span>
          </div>
          <div class="progress" style="height:6px">
            <div class="progress-bar bg-{{ $colors[$stage] ?? 'secondary' }}" style="width:{{ round($count/$total*100) }}%"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Top Customers --}}
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header">{{ __('pos.top_customers_ltv') }}</div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ __('pos.customers') }}</th>
              <th>{{ __('pos.ltv') }}</th>
              <th>{{ __('pos.purchases') }}</th>
              <th>{{ __('pos.stage') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach($stats['top_customers'] as $i => $c)
            <tr>
              <td>{{ $i+1 }}</td>
              <td>{{ $c->name }}</td>
              <td class="fw-bold">{{ number_format($c->lifetime_value, 2) }}</td>
              <td>{{ $c->purchase_count }}</td>
              <td>
                <span class="badge bg-{{ $colors[$c->lifecycle_stage] ?? 'secondary' }}">
                  {{ $stageLabels[$c->lifecycle_stage] ?? ucfirst(str_replace('_',' ',$c->lifecycle_stage)) }}
                </span>
              </td>
              <td>
                <a href="{{ route('crm.customer', $c->id) }}" class="btn btn-xs btn-outline-primary btn-sm">
                  {{ __('pos.view') }}
                </a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- Pending Follow-ups --}}
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between">
    <span>📅 {{ __('pos.upcoming_followups') }}</span>
    <button class="btn btn-sm btn-outline-secondary" id="refreshFollowUpsBtn">{{ __('pos.refresh') }}</button>
  </div>
  <div id="followUpsContainer" class="card-body p-0">
    <div class="text-center text-muted py-3">{{ __('pos.loading') }}…</div>
  </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const NO_FOLLOWUPS = @json(__('pos.no_pending_followups'));
const TH_CUSTOMER  = @json(__('pos.customers'));
const TH_TYPE      = @json(__('pos.type') ?? 'Type');
const TH_SUBJECT   = @json(__('pos.subject'));
const TH_SCHEDULED = @json(__('pos.scheduled'));

async function loadFollowUps() {
  const res  = await fetch('/api/crm/follow-ups', { headers: { 'Accept': 'application/json' } });
  const data = await res.json();
  const c    = document.getElementById('followUpsContainer');

  if (!data.length) {
    c.innerHTML = `<div class="text-center text-muted py-3">${esc(NO_FOLLOWUPS)}</div>`;
    return;
  }

  c.innerHTML = `<table class="table table-sm mb-0">
    <thead><tr><th>${esc(TH_CUSTOMER)}</th><th>${esc(TH_TYPE)}</th><th>${esc(TH_SUBJECT)}</th><th>${esc(TH_SCHEDULED)}</th></tr></thead>
    <tbody>${data.map(f => `
      <tr>
        <td>${esc(f.customer?.name ?? '-')}</td>
        <td>${f.type_icon ?? ''} ${esc(f.type)}</td>
        <td>${esc(f.subject ?? '-')}</td>
        <td>${esc(f.scheduled_at ?? '-')}</td>
      </tr>`).join('')}
    </tbody>
  </table>`;
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

document.getElementById('refreshFollowUpsBtn').addEventListener('click', loadFollowUps);
loadFollowUps();
</script>
@endpush
