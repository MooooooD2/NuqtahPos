@extends('layouts.app')
@section('title',      app()->getLocale() === 'ar' ? 'الأجهزة النشطة' : 'Active Devices')
@section('page-title', '🔐 ' . (app()->getLocale() === 'ar' ? 'الأجهزة والجلسات النشطة' : 'Active Devices & Sessions'))

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="card border-0 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="fas fa-shield-halved me-2 text-primary"></i>
            {{ $isAr ? 'جلساتك النشطة' : 'Your Active Sessions' }}
        </span>
        <button class="btn btn-sm btn-outline-danger" id="revokeAllBtn">
            <i class="fas fa-sign-out-alt me-1"></i>
            {{ $isAr ? 'تسجيل الخروج من جميع الأجهزة الأخرى' : 'Sign Out All Other Devices' }}
        </button>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">

            @forelse($sessions as $session)
            <div class="list-group-item d-flex justify-content-between align-items-center py-3"
                 id="sess-{{ $session->id }}">
                <div class="d-flex align-items-center gap-3">
                    {{-- Device icon --}}
                    <div class="fs-2 lh-1">
                        @if($session->device_type === 'mobile')
                            <i class="fas fa-mobile-screen text-primary"></i>
                        @elseif($session->device_type === 'tablet')
                            <i class="fas fa-tablet-screen-button text-info"></i>
                        @else
                            <i class="fas fa-laptop text-secondary"></i>
                        @endif
                    </div>

                    <div>
                        <div class="fw-semibold">
                            {{ $session->device_name ?? ($isAr ? 'جهاز غير معروف' : 'Unknown Device') }}
                            @if($session->is_current)
                                <span class="badge bg-success {{ $isAr ? 'me-1' : 'ms-1' }}">
                                    {{ $isAr ? 'الجلسة الحالية' : 'Current' }}
                                </span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            {{ $isAr ? 'IP:' : 'IP:' }} {{ $session->ip_address ?? ($isAr ? 'غير معروف' : 'Unknown') }}
                            &nbsp;·&nbsp;
                            {{ $isAr ? 'آخر نشاط:' : 'Last active:' }}
                            {{ $session->last_active_at?->diffForHumans() ?? ($isAr ? 'غير معروف' : 'Unknown') }}
                        </div>
                        @if($session->location)
                        <div class="small text-muted">
                            <i class="fas fa-location-dot me-1"></i>{{ $session->location }}
                        </div>
                        @endif
                    </div>
                </div>

                @if(!$session->is_current)
                <button class="btn btn-sm btn-outline-danger"
                        data-action="revoke-session"
                        data-id="{{ $session->id }}">
                    <i class="fas fa-times me-1"></i>
                    {{ $isAr ? 'إلغاء' : 'Revoke' }}
                </button>
                @else
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                    <i class="fas fa-circle me-1" style="font-size:8px"></i>
                    {{ $isAr ? 'نشط الآن' : 'Active Now' }}
                </span>
                @endif
            </div>
            @empty
            <div class="list-group-item text-center text-muted py-5">
                <i class="fas fa-shield-check fa-2x mb-2 d-block opacity-25"></i>
                {{ $isAr ? 'لا توجد جلسات نشطة' : 'No active sessions found' }}
            </div>
            @endforelse

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script @nonce>
const _isAr = LOCALE === 'ar';

// Revoke a single session
document.addEventListener('click', async function (e) {
    const btn = e.target.closest('[data-action="revoke-session"]');
    if (!btn) return;

    const id  = btn.dataset.id;
    const msg = _isAr ? 'إلغاء هذه الجلسة؟' : 'Revoke this session?';
    if (!confirm(msg)) return;

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const res = await fetch(`/device-sessions/${id}`, {
        method:  'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });

    if (res.ok) {
        const row = document.getElementById(`sess-${id}`);
        row?.remove();
        showToast(_isAr ? 'تم إلغاء الجلسة' : 'Session revoked', 'success');
    } else {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-times me-1"></i>${_isAr ? 'إلغاء' : 'Revoke'}`;
        showToast(_isAr ? 'فشل الإلغاء' : 'Revoke failed', 'danger');
    }
});

// Revoke all other sessions
document.getElementById('revokeAllBtn').addEventListener('click', async function () {
    const msg = _isAr ? 'تسجيل الخروج من جميع الأجهزة الأخرى؟' : 'Sign out all other devices?';
    if (!confirm(msg)) return;

    this.disabled = true;
    this.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>${_isAr ? 'جاري…' : 'Working…'}`;

    const res = await fetch('/device-sessions', {
        method:  'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
    });

    if (res.ok) {
        showToast(_isAr ? 'تم تسجيل الخروج من جميع الأجهزة الأخرى' : 'Signed out of all other devices', 'success');
        setTimeout(() => location.reload(), 1200);
    } else {
        this.disabled = false;
        this.innerHTML = `<i class="fas fa-sign-out-alt me-1"></i>${_isAr ? 'تسجيل الخروج من جميع الأجهزة الأخرى' : 'Sign Out All Other Devices'}`;
        showToast(_isAr ? 'فشلت العملية' : 'Operation failed', 'danger');
    }
});
</script>
@endpush
