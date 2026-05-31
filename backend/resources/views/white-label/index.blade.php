@extends('layouts.app')
@section('title', __('pos.white_label_settings'))
@section('page-title', '🎨 ' . __('pos.white_label_settings'))

@section('content')

<div class="row g-4">

  {{-- Left: Branding Form --}}
  <div class="col-lg-8">

    {{-- App Identity --}}
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-store me-2 text-primary"></i>
          {{ app()->getLocale()==='ar' ? 'هوية التطبيق' : 'App Identity' }}
        </h6>
      </div>
      <div class="card-body">
        <form id="brandingForm" enctype="multipart/form-data">
          @csrf
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'اسم التطبيق' : 'App Name' }}</label>
              <input type="text" name="app_name" id="appName" class="form-control"
                     value="{{ $wl->app_name ?? config('app.name') }}"
                     placeholder="{{ config('app.name') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('pos.font_family') }}</label>
              <select name="font_family" id="fontFamily" class="form-select">
                @foreach(['Tajawal','Cairo','IBM Plex Sans Arabic','Inter','Roboto','Poppins'] as $font)
                  <option value="{{ $font }}" {{ ($wl->font_family ?? 'Tajawal') === $font ? 'selected' : '' }}>{{ $font }}</option>
                @endforeach
              </select>
            </div>
          </div>

          {{-- Logos --}}
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('pos.upload_logo') }}</label>
              <input type="file" name="logo" id="logoInput" class="form-control" accept="image/*">
              @if($wl?->logo_path)
                <div class="mt-2">
                  <img src="{{ $wl->logo_url }}" alt="Logo" class="img-thumbnail" style="max-height:60px">
                </div>
              @endif
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('pos.upload_favicon') }}</label>
              <input type="file" name="favicon" id="faviconInput" class="form-control" accept="image/x-icon,image/png,image/svg+xml">
              @if($wl?->favicon_path)
                <div class="mt-2">
                  <img src="{{ $wl->favicon_url }}" alt="Favicon" class="img-thumbnail" style="max-height:32px">
                </div>
              @endif
            </div>
          </div>

          {{-- Footer / Support --}}
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <label class="form-label fw-semibold">{{ __('pos.footer_text') }}</label>
              <input type="text" name="footer_text" class="form-control"
                     value="{{ $wl->footer_text ?? '' }}"
                     placeholder="{{ app()->getLocale()==='ar' ? 'نص التذييل…' : 'Footer text…' }}">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'إيميل الدعم' : 'Support Email' }}</label>
              <input type="email" name="support_email" class="form-control" value="{{ $wl->support_email ?? '' }}">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'هاتف الدعم' : 'Support Phone' }}</label>
              <input type="text" name="support_phone" class="form-control" value="{{ $wl->support_phone ?? '' }}">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">{{ app()->getLocale()==='ar' ? 'موقع الدعم' : 'Support Website' }}</label>
              <input type="url" name="support_website" class="form-control" value="{{ $wl->support_website ?? '' }}">
            </div>
          </div>

          {{-- Hide Powered By --}}
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="hide_powered_by" id="hidePoweredBy"
                   {{ $wl?->hide_powered_by ? 'checked' : '' }}>
            <label class="form-check-label" for="hidePoweredBy">{{ __('pos.hide_powered_by') }}</label>
          </div>

          {{-- Colors --}}
          <hr class="my-3">
          <h6 class="fw-semibold mb-3"><i class="fas fa-palette me-2 text-warning"></i>{{ app()->getLocale()==='ar' ? 'الألوان' : 'Colors' }}</h6>
          <div class="row g-3 mb-3">
            @foreach([
              ['primary_color',   __('pos.primary_color'),   '#6366f1'],
              ['secondary_color', __('pos.secondary_color'), '#8b5cf6'],
              ['accent_color',    __('pos.accent_color'),    '#ec4899'],
              ['text_color',      __('pos.text_color'),      '#111827'],
              ['bg_color',        __('pos.bg_color'),        '#f9fafb'],
            ] as [$name, $label, $default])
            <div class="col-md-4 col-6">
              <label class="form-label fw-semibold small">{{ $label }}</label>
              <div class="input-group">
                <input type="color" name="{{ $name }}" class="form-control form-control-color color-swatch"
                       data-target="{{ $name }}_hex"
                       value="{{ $wl?->$name ?? $default }}" title="{{ $label }}">
                <input type="text" id="{{ $name }}_hex" class="form-control form-control-sm hex-input"
                       value="{{ $wl?->$name ?? $default }}" maxlength="7">
              </div>
            </div>
            @endforeach
          </div>

          {{-- Custom CSS --}}
          <hr class="my-3">
          <h6 class="fw-semibold mb-2"><i class="fas fa-code me-2 text-info"></i>{{ __('pos.custom_css') }}</h6>
          <textarea name="custom_css" class="form-control font-monospace" rows="8"
                    placeholder="/* {{ app()->getLocale()==='ar' ? 'أضف CSS مخصص هنا…' : 'Add custom CSS here…' }} */"
                    style="font-size:.85rem">{{ $wl->custom_css ?? '' }}</textarea>

          <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary" id="saveBrandingBtn">
              <i class="fas fa-save me-2"></i>{{ app()->getLocale()==='ar' ? 'حفظ التغييرات' : 'Save Changes' }}
            </button>
            <button type="button" class="btn btn-outline-secondary" id="btnResetPreview">
              <i class="fas fa-rotate-left me-2"></i>{{ app()->getLocale()==='ar' ? 'إعادة تعيين' : 'Reset' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    {{-- Custom Domain --}}
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-globe me-2 text-success"></i>{{ __('pos.custom_domain') }}</h6>
      </div>
      <div class="card-body">
        <div class="alert alert-info small mb-3">
          <i class="fas fa-info-circle me-2"></i>
          {{ app()->getLocale()==='ar'
            ? 'أضف سجل CNAME يشير إلى ' . request()->getHost() . ' ثم اضغط تحقق.'
            : 'Add a CNAME record pointing to ' . request()->getHost() . ' then click Verify.' }}
        </div>
        <div class="row g-2 align-items-end">
          <div class="col">
            <label class="form-label fw-semibold">{{ __('pos.custom_domain') }}</label>
            <input type="text" id="domainInput" class="form-control"
                   value="{{ $wl->custom_domain ?? '' }}"
                   placeholder="pos.mystore.com">
          </div>
          <div class="col-auto">
            @if($wl?->domain_verified)
              <span class="badge bg-success fs-6 px-3 py-2">
                <i class="fas fa-check-circle me-1"></i>{{ __('pos.domain_verified') }}
              </span>
            @else
              <button class="btn btn-outline-primary" id="btnSaveDomain">
                <i class="fas fa-link me-1"></i>{{ app()->getLocale()==='ar' ? 'حفظ' : 'Save' }}
              </button>
              <button class="btn btn-success {{ $wl?->custom_domain ? '' : 'disabled' }}" id="verifyBtn">
                <i class="fas fa-shield-check me-1"></i>{{ __('pos.verify_domain') }}
              </button>
            @endif
          </div>
        </div>
        <div id="domainMsg" class="mt-2"></div>
      </div>
    </div>
  </div>

  {{-- Right: Live Preview --}}
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm sticky-top" style="top:80px">
      <div class="card-header bg-transparent">
        <h6 class="mb-0 fw-semibold"><i class="fas fa-eye me-2 text-secondary"></i>{{ app()->getLocale()==='ar' ? 'معاينة مباشرة' : 'Live Preview' }}</h6>
      </div>
      <div class="card-body p-2">
        <div id="preview" class="rounded-3 overflow-hidden border" style="min-height:320px">
          <div id="previewSidebar" class="p-3 text-white d-flex flex-column gap-2" style="background:var(--preview-primary,#6366f1);min-height:320px;width:140px;float:left">
            <div class="fw-bold small mb-2" id="previewAppName">{{ $wl->app_name ?? config('app.name') }}</div>
            @foreach(['Dashboard','POS','Warehouse','Reports'] as $item)
              <div class="small opacity-75 py-1 px-2 rounded" style="background:rgba(255,255,255,.1)">{{ $item }}</div>
            @endforeach
          </div>
          <div class="p-3" id="previewContent" style="min-height:320px;background:var(--preview-bg,#f9fafb)">
            <div class="fw-semibold mb-2" id="previewTitle" style="color:var(--preview-text,#111827)">{{ app()->getLocale()==='ar' ? 'معاينة' : 'Preview' }}</div>
            <div class="d-flex gap-2 mb-2">
              <div class="rounded px-2 py-1 text-white small" id="previewBtn" style="background:var(--preview-primary,#6366f1)">{{ app()->getLocale()==='ar' ? 'زر رئيسي' : 'Primary Btn' }}</div>
              <div class="rounded px-2 py-1 text-white small" id="previewAccent" style="background:var(--preview-accent,#ec4899)">Accent</div>
            </div>
            <div class="small opacity-50" id="previewFooter">{{ $wl->footer_text ?? '© 2026' }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection

@push('scripts')
<script @nonce>
// ── Color sync: color picker ↔ hex input ──────────────────────────────────
document.querySelectorAll('.color-swatch').forEach(picker => {
  const hexInput = document.getElementById(picker.dataset.target);
  picker.addEventListener('input', () => {
    if (hexInput) hexInput.value = picker.value;
    updatePreview();
  });
  if (hexInput) {
    hexInput.addEventListener('input', () => {
      if (/^#[0-9a-f]{6}$/i.test(hexInput.value)) {
        picker.value = hexInput.value;
        updatePreview();
      }
    });
  }
});

function updatePreview() {
  const get = name => document.querySelector(`[name="${name}"]`)?.value ?? '';
  document.getElementById('previewSidebar').style.background  = get('primary_color')   || '#6366f1';
  document.getElementById('previewContent').style.background  = get('bg_color')        || '#f9fafb';
  document.getElementById('previewTitle').style.color         = get('text_color')      || '#111827';
  document.getElementById('previewBtn').style.background      = get('primary_color')   || '#6366f1';
  document.getElementById('previewAccent').style.background   = get('accent_color')    || '#ec4899';
  const appName = document.getElementById('appName')?.value;
  if (appName) document.getElementById('previewAppName').textContent = appName;
  const footer = document.querySelector('[name="footer_text"]')?.value;
  if (footer !== undefined) document.getElementById('previewFooter').textContent = footer || '© 2026';
}

// ── Button listeners (no inline handlers) ────────────────────────────────
document.getElementById('btnResetPreview')?.addEventListener('click', updatePreview);
document.getElementById('btnSaveDomain')?.addEventListener('click', saveDomain);
document.getElementById('verifyBtn')?.addEventListener('click', verifyDomain);

// Live preview on app name change
document.getElementById('appName')?.addEventListener('input', updatePreview);
document.querySelector('[name="footer_text"]')?.addEventListener('input', updatePreview);

updatePreview();

// ── Save branding ─────────────────────────────────────────────────────────
document.getElementById('brandingForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('saveBrandingBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ app()->getLocale()==='ar' ? 'جاري الحفظ…' : 'Saving…' }}';

  const fd = new FormData(e.target);
  if (!document.getElementById('hidePoweredBy').checked) fd.delete('hide_powered_by');

  const res  = await fetch('/api/white-label', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: fd });
  const data = await res.json();

  if (data.success) {
    Swal.fire({ icon: 'success', title: '{{ __('pos.branding_saved') }}', timer: 2000, showConfirmButton: false });
  } else {
    Swal.fire({ icon: 'error', title: 'Error', text: data.message ?? JSON.stringify(data.errors ?? '') });
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save me-2"></i>{{ app()->getLocale()==='ar' ? 'حفظ التغييرات' : 'Save Changes' }}';
});

// ── Domain helpers ────────────────────────────────────────────────────────
async function saveDomain() {
  const domain = document.getElementById('domainInput').value.trim();
  const res  = await fetch('/api/white-label/domain', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
    body: JSON.stringify({ domain }),
  });
  const data = await res.json();
  const msg  = document.getElementById('domainMsg');
  if (data.success) {
    msg.innerHTML = '<div class="alert alert-success small py-2 mb-0"><i class="fas fa-check me-1"></i>' + (data.message ?? 'Saved') + '</div>';
    document.getElementById('verifyBtn')?.classList.remove('disabled');
  } else {
    msg.innerHTML = '<div class="alert alert-danger small py-2 mb-0">' + (data.message ?? 'Error') + '</div>';
  }
}

async function verifyDomain() {
  const res  = await fetch('/api/white-label/verify-domain', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
  });
  const data = await res.json();
  const msg  = document.getElementById('domainMsg');
  if (data.verified) {
    msg.innerHTML = '<div class="alert alert-success small py-2 mb-0"><i class="fas fa-shield-check me-1"></i>{{ __('pos.domain_verified') }}</div>';
    setTimeout(() => location.reload(), 1500);
  } else {
    msg.innerHTML = `<div class="alert alert-warning small py-2 mb-0">${data.message ?? '{{ app()->getLocale()==='ar' ? 'تحقق من سجل DNS' : 'DNS record not found yet' }}'}</div>`;
  }
}
</script>
@endpush
