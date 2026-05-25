@extends('layouts.app')

@section('title', 'Charge Codes')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Invoice</li>
    <li class="breadcrumb-item active">Charge Codes</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tag me-2 text-primary"></i>Charge Codes</h4>
        <p class="text-muted mb-0 small">Define billable charge items for invoices, tariffs and supplier payables. Drag rows to reorder.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Charge Code
    </button>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Category filter tabs --}}
<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
    <a href="{{ route('masters.charge-codes.index') }}"
       class="btn btn-sm {{ !request('category') ? 'btn-dark' : 'btn-outline-secondary' }}">
        All <span class="badge bg-white text-dark ms-1">{{ $chargeCodes->count() }}</span>
    </a>
    @foreach($categories as $key => $label)
        @php $count = $chargeCodes->where('category', $key)->count(); @endphp
        <a href="{{ route('masters.charge-codes.index', ['category' => $key]) }}"
           class="btn btn-sm {{ request('category') === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ $label }}
            @if($count)<span class="badge {{ request('category') === $key ? 'bg-white text-primary' : 'bg-secondary' }} ms-1">{{ $count }}</span>@endif
        </a>
    @endforeach
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" id="chargeTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th style="width:110px;">Code</th>
                    <th>Description</th>
                    <th style="width:160px;">Category</th>
                    <th style="width:140px;">Rate Type</th>
                    <th style="width:190px;">Applicable Tax</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:100px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody id="sortableBody">
            @forelse($chargeCodes as $cc)
                <tr data-id="{{ $cc->id }}" class="{{ $cc->is_active ? '' : 'table-secondary text-muted' }}">
                    <td class="ps-3 drag-handle" style="cursor:grab;" title="Drag to reorder">
                        <i class="bi bi-grip-vertical text-muted"></i>
                    </td>
                    <td class="small text-muted fw-semibold">{{ $cc->sort_order }}</td>
                    <td>
                        <span class="badge bg-primary fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $cc->code }}
                        </span>
                    </td>
                    <td class="small fw-semibold">{{ $cc->description }}</td>
                    <td>
                        @if($cc->category)
                            <span class="badge {{ $cc->category_badge }} small">
                                {{ $cc->category_label }}
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        @if($cc->rate_type)
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle small">
                                {{ $cc->rate_type_label }}
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        @if($cc->taxCode)
                            <span class="badge bg-info-subtle text-info border border-info-subtle me-1">
                                {{ $cc->taxCode->code }}
                            </span>
                            <span class="text-muted small">{{ $cc->taxCode->description }}</span>
                        @else
                            <span class="badge bg-light border text-muted">No Tax</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('masters.charge-codes.toggle', $cc) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="btn btn-sm {{ $cc->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                    title="{{ $cc->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                <i class="bi {{ $cc->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-edit"
                                    data-id="{{ $cc->id }}"
                                    data-code="{{ $cc->code }}"
                                    data-description="{{ $cc->description }}"
                                    data-category="{{ $cc->category ?? '' }}"
                                    data-rate_type="{{ $cc->rate_type ?? '' }}"
                                    data-tax_code_id="{{ $cc->tax_code_id ?? '' }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-delete"
                                    data-id="{{ $cc->id }}"
                                    data-label="{{ $cc->code }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-tag fs-3 d-block mb-2"></i>
                        No charge codes found.
                        @if(request('category'))
                            <a href="{{ route('masters.charge-codes.index') }}">Clear filter</a> or
                        @endif
                        click <strong>Add Charge Code</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $chargeCodes->count() }} code(s) shown &middot; {{ $chargeCodes->where('is_active', true)->count() }} active
        </span>
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.charge-codes.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Charge Code</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase"
                                   maxlength="20" required placeholder="e.g. STC">
                            <div class="form-text">Short unique code (max 20)</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control"
                                   maxlength="200" required placeholder="e.g. Storage Charges">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" class="form-select">
                                <option value="">— Select Category —</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rate Type</label>
                            <select name="rate_type" class="form-select">
                                <option value="">— Select Rate Type —</option>
                                @foreach($rateTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Applicable Tax Code</label>
                            <select name="tax_code_id" class="form-select">
                                <option value="">— No Tax / Exempt —</option>
                                @foreach($taxCodes as $tc)
                                    <option value="{{ $tc->id }}">{{ $tc->code }} — {{ $tc->description }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Modal ── --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Charge Code</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode"
                                   class="form-control text-uppercase" maxlength="20" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="editDescription"
                                   class="form-control" maxlength="200" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" id="editCategory" class="form-select">
                                <option value="">— Select Category —</option>
                                @foreach($categories as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rate Type</label>
                            <select name="rate_type" id="editRateType" class="form-select">
                                <option value="">— Select Rate Type —</option>
                                @foreach($rateTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Applicable Tax Code</label>
                            <select name="tax_code_id" id="editTaxCodeId" class="form-select">
                                <option value="">— No Tax / Exempt —</option>
                                @foreach($taxCodes as $tc)
                                    <option value="{{ $tc->id }}">{{ $tc->code }} — {{ $tc->description }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Delete Modal ── --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Charge Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete charge code <strong id="deleteLabel"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
// ── Drag-to-reorder ──────────────────────────────────────────────────────────
const tbody = document.getElementById('sortableBody');
if (tbody) {
    Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd() {
            const order = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
            fetch('{{ route("masters.charge-codes.reorder") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order }),
            }).then(() => {
                tbody.querySelectorAll('tr[data-id]').forEach((row, idx) => {
                    const cell = row.cells[1];
                    if (cell) cell.textContent = idx + 1;
                });
            });
        },
    });
}

// ── Edit modal ───────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editCode').value        = btn.dataset.code;
        document.getElementById('editDescription').value = btn.dataset.description;
        document.getElementById('editCategory').value    = btn.dataset.category || '';
        document.getElementById('editRateType').value    = btn.dataset.rate_type || '';
        document.getElementById('editTaxCodeId').value   = btn.dataset.tax_code_id || '';
        document.getElementById('editForm').action =
            '{{ url("masters/charge-codes") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/charge-codes") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
