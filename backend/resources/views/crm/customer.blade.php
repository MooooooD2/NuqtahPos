@extends('layouts.app')
@section('title', 'CRM — ' . $customer->name)
@section('page-title', '👤 ' . $customer->name)

@section('content')
@php
  $colors = ['lead'=>'secondary','prospect'=>'info','customer'=>'primary','loyal'=>'success','at_risk'=>'warning','churned'=>'danger'];
  $stage  = $customer->lifecycle_stage ?? 'customer';
  $stageLabels = [
    'lead'     => __('pos.stage_lead'),
    'prospect' => __('pos.stage_prospect'),
    'customer' => __('pos.stage_customer'),
    'loyal'    => __('pos.stage_loyal'),
    'at_risk'  => __('pos.stage_at_risk'),
    'churned'  => __('pos.stage_churned'),
  ];
@endphp

<div class="row g-4">
  {{-- Customer Profile --}}
  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">{{ $customer->name }}</h5>
        <p class="text-muted mb-1"><i class="fas fa-phone me-1"></i>{{ $customer->phone ?? __('pos.na') }}</p>
        <p class="text-muted mb-1"><i class="fas fa-envelope me-1"></i>{{ $customer->email ?? __('pos.na') }}</p>
        <hr>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('pos.lifecycle_stage') }}</span>
          <span class="badge bg-{{ $colors[$stage] ?? 'secondary' }}">
            {{ $stageLabels[$stage] ?? ucfirst(str_replace('_',' ',$stage)) }}
          </span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('pos.lifetime_value') }}</span>
          <strong>{{ number_format($customer->lifetime_value ?? 0, 2) }}</strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('pos.total_purchases') }}</span>
          <strong>{{ $customer->purchase_count ?? 0 }}</strong>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('pos.loyalty_points') }}</span>
          <strong>{{ $customer->loyalty_points ?? 0 }}</strong>
        </div>
        <div class="d-flex justify-content-between">
          <span>{{ __('pos.cashback_balance') }}</span>
          <strong class="text-success">{{ number_format($customer->cashback_balance ?? 0, 2) }}</strong>
        </div>
      </div>
    </div>

    {{-- Recent Invoices --}}
    <div class="card">
      <div class="card-header">{{ __('pos.recent_invoices') }}</div>
      <div class="list-group list-group-flush">
        @foreach($invoices as $inv)
        <div class="list-group-item d-flex justify-content-between py-2">
          <span class="small">#{{ $inv->invoice_number }}</span>
          <span class="small fw-bold">{{ number_format($inv->final_total, 2) }}</span>
          <span class="small text-muted">{{ $inv->created_at?->format('M d') }}</span>
        </div>
        @endforeach
        @if($invoices->isEmpty())
        <div class="list-group-item text-muted text-center small py-3">{{ __('pos.no_invoices') }}</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Activity Log --}}
  <div class="col-md-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ __('pos.activity_log') }}</span>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
          <i class="fas fa-plus me-1"></i> {{ __('pos.log_activity') }}
        </button>
      </div>
      <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
        <div id="activitiesContainer">
          @php
            $actLabels = [
              'call'      => __('pos.act_call'),
              'email'     => __('pos.act_email'),
              'whatsapp'  => __('pos.act_whatsapp'),
              'visit'     => __('pos.act_visit'),
              'note'      => __('pos.act_note'),
              'complaint' => __('pos.act_complaint'),
              'follow_up' => __('pos.act_follow_up'),
            ];
            $outcomeColors = ['positive'=>'success','negative'=>'danger','neutral'=>'secondary','pending'=>'warning'];
            $outcomeLabels = [
              'positive' => __('pos.outcome_positive'),
              'negative' => __('pos.outcome_negative'),
              'neutral'  => __('pos.outcome_neutral'),
              'pending'  => __('pos.outcome_pending'),
            ];
          @endphp
          @foreach($activities as $act)
          <div class="border-bottom p-3" id="act-{{ $act->id }}">
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-semibold">
                {{ $act->type_icon }}
                {{ $actLabels[$act->type] ?? ucfirst($act->type) }}
                {{ $act->subject ? ' — ' . $act->subject : '' }}
              </span>
              <small class="text-muted">{{ $act->created_at->diffForHumans() }}</small>
            </div>
            @if($act->notes)
            <p class="text-muted small mb-1">{{ $act->notes }}</p>
            @endif
            <div class="d-flex gap-2 align-items-center">
              @php $oc = $act->outcome ?? 'neutral'; @endphp
              <span class="badge bg-{{ $outcomeColors[$oc] ?? 'secondary' }}">
                {{ $outcomeLabels[$oc] ?? ucfirst($oc) }}
              </span>
              @if($act->scheduled_at)
              <small class="text-muted">📅 {{ $act->scheduled_at->format('M d, H:i') }}</small>
              @endif
              <small class="text-muted ms-auto">by {{ $act->user?->full_name ?? __('pos.system') }}</small>
            </div>
          </div>
          @endforeach
          @if($activities->isEmpty())
          <div class="text-center text-muted py-5">{{ __('pos.no_activities') }}</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Add Activity Modal --}}
<div class="modal fade" id="addActivityModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('pos.log_activity') }}</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('pos.type') }}</label>
          <select class="form-select" id="actType">
            <option value="call">📞 {{ __('pos.act_call') }}</option>
            <option value="email">📧 {{ __('pos.act_email') }}</option>
            <option value="whatsapp">💬 {{ __('pos.act_whatsapp') }}</option>
            <option value="visit">🏪 {{ __('pos.act_visit') }}</option>
            <option value="note" selected>📝 {{ __('pos.act_note') }}</option>
            <option value="complaint">⚠️ {{ __('pos.act_complaint') }}</option>
            <option value="follow_up">🔔 {{ __('pos.act_follow_up') }}</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('pos.subject') }}</label>
          <input type="text" class="form-control" id="actSubject" placeholder="{{ __('pos.brief_summary') }}">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('pos.notes') }}</label>
          <textarea class="form-control" id="actNotes" rows="3" placeholder="{{ __('pos.detailed_notes') }}"></textarea>
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">{{ __('pos.outcome') }}</label>
            <select class="form-select" id="actOutcome">
              <option value="neutral">{{ __('pos.outcome_neutral') }}</option>
              <option value="positive">{{ __('pos.outcome_positive') }}</option>
              <option value="negative">{{ __('pos.outcome_negative') }}</option>
              <option value="pending">{{ __('pos.outcome_pending') }}</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">{{ __('pos.schedule_followup') }}</label>
            <input type="datetime-local" class="form-control" id="actScheduled">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">{{ __('pos.cancel') }}</button>
        <button class="btn btn-primary" id="saveActivityBtn">{{ __('pos.save_activity') }}</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;

document.getElementById('saveActivityBtn').addEventListener('click', async () => {
  const payload = {
    customer_id:  {{ $customer->id }},
    type:         document.getElementById('actType').value,
    subject:      document.getElementById('actSubject').value.trim() || null,
    notes:        document.getElementById('actNotes').value.trim() || null,
    outcome:      document.getElementById('actOutcome').value,
    scheduled_at: document.getElementById('actScheduled').value || null,
  };

  const btn = document.getElementById('saveActivityBtn');
  btn.disabled = true;

  const res  = await fetch('/api/crm/activities', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  btn.disabled = false;

  if (res.ok) {
    bootstrap.Modal.getInstance(document.getElementById('addActivityModal')).hide();
    location.reload();
  } else {
    alert(data.message ?? '{{ __('pos.error_saving') }}');
  }
});
</script>
@endpush
