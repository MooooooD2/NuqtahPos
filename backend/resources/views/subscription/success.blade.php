@extends('layouts.app')
@section('title', app()->getLocale() === 'ar' ? 'تم الدفع بنجاح' : 'Payment Successful')

@push('styles')
<style @nonce>
    .success-card {
        max-width: 560px; margin: 3rem auto; text-align: center;
        background: #fff; border-radius: 1.5rem; padding: 3rem 2.5rem;
        box-shadow: 0 20px 60px rgba(0,0,0,.08);
        border: 1.5px solid #bbf7d0;
    }
    .success-icon {
        width: 90px; height: 90px; border-radius: 50%;
        background: linear-gradient(135deg, #059669, #10b981);
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5rem; color: #fff; margin: 0 auto 1.5rem;
        box-shadow: 0 12px 30px rgba(5,150,105,.35);
        animation: pop .5s cubic-bezier(.175,.885,.32,1.275);
    }
    @keyframes pop { from{transform:scale(0);opacity:0} to{transform:scale(1);opacity:1} }
    .detail-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: .6rem .9rem; background: #f8fafc; border-radius: .625rem; margin-bottom: .5rem;
        font-size: .875rem;
    }
    .detail-row .label { color: #64748b; }
    .detail-row .value { font-weight: 700; color: #0f172a; }
</style>
@endpush

@section('content')
@php $isAr = app()->getLocale() === 'ar'; @endphp

<div class="success-card">
    <div class="success-icon"><i class="fas fa-check"></i></div>

    <h2 class="fw-bold mb-2" style="font-size:1.7rem;color:#0f172a">
        {{ $isAr ? 'تم الدفع بنجاح!' : 'Payment Successful!' }}
    </h2>
    <p class="text-muted mb-4" style="font-size:.95rem;line-height:1.7">
        {{ $isAr
            ? 'شكراً لك! تم تفعيل اشتراكك وأصبح بإمكانك استخدام النظام بالكامل الآن.'
            : 'Thank you! Your subscription is now active and you have full access to the system.' }}
    </p>

    <div class="mb-4">
        <div class="detail-row">
            <span class="label"><i class="fas fa-store me-2 text-primary"></i>{{ $isAr ? 'المتجر' : 'Store' }}</span>
            <span class="value">{{ $tenant->name }}</span>
        </div>
        <div class="detail-row">
            <span class="label"><i class="fas fa-receipt me-2 text-primary"></i>{{ $isAr ? 'رقم العملية' : 'Transaction' }}</span>
            <span class="value" style="font-family:monospace;font-size:.8rem">{{ Str::upper(Str::substr($session->id, -12)) }}</span>
        </div>
        <div class="detail-row">
            <span class="label"><i class="fas fa-dollar-sign me-2 text-success"></i>{{ $isAr ? 'المبلغ المدفوع' : 'Amount Paid' }}</span>
            <span class="value text-success">${{ number_format($session->amount_total / 100, 2) }}</span>
        </div>
        <div class="detail-row">
            <span class="label"><i class="fas fa-calendar-check me-2 text-primary"></i>{{ $isAr ? 'ينتهي في' : 'Active Until' }}</span>
            <span class="value">{{ $tenant->fresh()->subscription_ends_at?->format('d M Y') ?? '—' }}</span>
        </div>
    </div>

    <a href="{{ route('dashboard') }}" class="btn w-100 fw-bold py-2 mb-2"
       style="background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;border:none;border-radius:.75rem;font-size:1rem">
        <i class="fas fa-tachometer-alt me-2"></i>
        {{ $isAr ? 'الذهاب إلى لوحة التحكم' : 'Go to Dashboard' }}
    </a>
    <p class="text-muted small mt-2">
        <i class="fas fa-envelope me-1"></i>
        {{ $isAr ? 'تم إرسال إيصال الدفع إلى بريدك الإلكتروني.' : 'A payment receipt was sent to your email.' }}
    </p>
</div>
@endsection
