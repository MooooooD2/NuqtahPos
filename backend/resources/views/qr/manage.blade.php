@extends('layouts.app')
@section('title', 'QR Tables')
@section('page-title', 'QR Ordering — Tables')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <p class="text-muted mb-0">Generate QR codes for tables. Customers scan to view menu and order.</p>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTableModal">
    <i class="fas fa-plus me-1"></i> Add Table
  </button>
</div>

<div class="row g-3" id="tablesGrid">
  @foreach($tables as $table)
  <div class="col-md-4 col-lg-3">
    <div class="card text-center">
      <div class="card-body">
        <div class="mb-2">
          <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode(route('qr.menu', $table->token)) }}"
               alt="QR Code" class="img-fluid" style="max-width:150px" loading="lazy">
        </div>
        <h5 class="card-title">{{ $table->table_name }}</h5>
        <p class="text-muted small mb-1">Capacity: {{ $table->capacity }} | Today: {{ $table->orders_count }} orders</p>
        <div class="input-group input-group-sm mb-2">
          <input type="text" class="form-control" value="{{ route('qr.menu', $table->token) }}" readonly id="url-{{ $table->id }}">
          <button class="btn btn-outline-secondary" onclick="copyUrl({{ $table->id }})"><i class="fas fa-copy"></i></button>
        </div>
        <div class="d-flex gap-1 justify-content-center">
          <a href="{{ route('qr.menu', $table->token) }}" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-external-link-alt"></i> Preview
          </a>
          <a href="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data={{ urlencode(route('qr.menu', $table->token)) }}"
             download="qr-{{ Str::slug($table->table_name) }}.png"
             class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-download"></i> QR
          </a>
          <span class="badge {{ $table->is_active ? 'bg-success' : 'bg-danger' }} d-flex align-items-center">
            {{ $table->is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>
      </div>
    </div>
  </div>
  @endforeach
</div>

{{-- Add Table Modal --}}
<div class="modal fade" id="addTableModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add QR Table</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Table Name</label>
          <input type="text" class="form-control" id="newTableName" placeholder="e.g. Table 1, Terrace A">
        </div>
        <div class="mb-3">
          <label class="form-label">Capacity (seats)</label>
          <input type="number" class="form-control" id="newCapacity" value="4" min="1" max="50">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="createTableBtn">Create & Generate QR</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script @nonce>
function copyUrl(id) {
  const input = document.getElementById(`url-${id}`);
  input.select();
  document.execCommand('copy');
  // brief feedback
  const btn = input.nextElementSibling;
  btn.innerHTML = '<i class="fas fa-check"></i>';
  setTimeout(() => btn.innerHTML = '<i class="fas fa-copy"></i>', 1500);
}

document.getElementById('createTableBtn').addEventListener('click', async () => {
  const name = document.getElementById('newTableName').value.trim();
  const cap  = parseInt(document.getElementById('newCapacity').value) || 4;
  if (!name) { alert('Enter a table name'); return; }

  const btn = document.getElementById('createTableBtn');
  btn.disabled = true;

  const res = await fetch('{{ route("qr-tables.generate") }}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    body: JSON.stringify({ table_name: name, capacity: cap }),
  });
  const data = await res.json();

  btn.disabled = false;
  if (res.ok) {
    bootstrap.Modal.getInstance(document.getElementById('addTableModal')).hide();
    location.reload();
  } else {
    alert(data.message ?? 'Error');
  }
});
</script>
@endpush
