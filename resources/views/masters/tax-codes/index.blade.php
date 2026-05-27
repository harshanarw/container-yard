@extends('layouts.app')

@section('title', 'Tax Codes')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Invoice</li>
    <li class="breadcrumb-item active">Tax Codes</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-percent me-2 text-primary"></i>Tax Codes</h4>
        <p class="text-muted mb-0 small">Define tax combinations applied to charges and invoices. Drag rows to reorder.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Tax Code
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

{{-- Tax Label Settings --}}
<div class="card content-card mb-4">
    <div class="card-header py-2 d-flex align-items-center justify-content-between">
        <span><i class="bi bi-tag me-2 text-primary"></i>Tax Column Labels</span>
        <small class="text-muted">Customise the names displayed for Tax 1 and Tax 2 throughout the system</small>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('masters.tax-codes.labels') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Tax 1 Label</label>
                <input type="text" name="tax1_label"
                       class="form-control @error('tax1_label') is-invalid @enderror"
                       value="{{ old('tax1_label', $settings->tax1_label ?? 'Tax 1') }}"
                       maxlength="50" placeholder="e.g. SSCL">
                @error('tax1_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Name for the first tax column (e.g. SSCL, GST, Levy)</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold">Tax 2 Label</label>
                <input type="text" name="tax2_label"
                       class="form-control @error('tax2_label') is-invalid @enderror"
                       value="{{ old('tax2_label', $settings->tax2_label ?? 'Tax 2') }}"
                       maxlength="50" placeholder="e.g. VAT">
                @error('tax2_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Name for the second tax column (e.g. VAT, CGST, SGST)</div>
            </div>
            <div class="col-12 col-md-4">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-save me-1"></i>Save Labels
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Tax Codes Table --}}
<div class="card content-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" id="taxTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th style="width:120px;">Code</th>
                    <th>Description</th>
                    <th style="width:110px;" class="text-center">{{ $settings->tax1_label ?? 'Tax 1' }} %</th>
                    <th style="width:110px;" class="text-center">{{ $settings->tax2_label ?? 'Tax 2' }} %</th>
                    <th style="width:100px;" class="text-center">Total %</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:100px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody id="sortableBody">
            @forelse($taxCodes as $tc)
                <tr data-id="{{ $tc->id }}" class="{{ $tc->is_active ? '' : 'table-secondary text-muted' }}">
                    <td class="ps-3 drag-handle" style="cursor:grab;" title="Drag to reorder">
                        <i class="bi bi-grip-vertical text-muted"></i>
                    </td>
                    <td class="small text-muted fw-semibold">{{ $tc->sort_order }}</td>
                    <td>
                        <span class="badge bg-primary fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $tc->code }}
                        </span>
                    </td>
                    <td class="small">{{ $tc->description }}</td>
                    <td class="text-center">
                        @if($tc->tax1_rate > 0)
                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                {{ rtrim(rtrim(number_format($tc->tax1_rate, 4), '0'), '.') }}%
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($tc->tax2_rate > 0)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                {{ rtrim(rtrim(number_format($tc->tax2_rate, 4), '0'), '.') }}%
                            </span>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($tc->total_rate > 0)
                            <span class="badge bg-dark text-white fw-semibold">
                                {{ rtrim(rtrim(number_format($tc->total_rate, 4), '0'), '.') }}%
                            </span>
                        @else
                            <span class="badge bg-light border text-muted">Exempt</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('masters.tax-codes.toggle', $tc) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="btn btn-sm {{ $tc->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                    title="{{ $tc->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                <i class="bi {{ $tc->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                    data-id="{{ $tc->id }}"
                                    data-code="{{ $tc->code }}"
                                    data-description="{{ $tc->description }}"
                                    data-tax1_rate="{{ $tc->tax1_rate }}"
                                    data-tax2_rate="{{ $tc->tax2_rate }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    data-id="{{ $tc->id }}"
                                    data-label="{{ $tc->code }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-percent fs-3 d-block mb-2"></i>
                        No tax codes yet. Click <strong>Add Tax Code</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $taxCodes->count() }} code(s) total · {{ $taxCodes->where('is_active', true)->count() }} active
        </span>
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.tax-codes.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Tax Code</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase"
                                   maxlength="50" required placeholder="e.g. VAT18">
                            <div class="form-text">Short unique identifier</div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" class="form-control"
                                   maxlength="200" required placeholder="e.g. VAT 18%">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ $settings->tax1_label ?? 'Tax 1' }} % <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="tax1_rate" class="form-control"
                                       min="0" max="100" step="0.01" value="0" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Enter 0 if not applicable</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ $settings->tax2_label ?? 'Tax 2' }} % <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="tax2_rate" class="form-control"
                                       min="0" max="100" step="0.01" value="0" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Enter 0 if not applicable</div>
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
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Tax Code</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode" class="form-control text-uppercase"
                                   maxlength="50" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="editDescription" class="form-control"
                                   maxlength="200" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ $settings->tax1_label ?? 'Tax 1' }} % <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="tax1_rate" id="editTax1Rate" class="form-control"
                                       min="0" max="100" step="0.01" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">{{ $settings->tax2_label ?? 'Tax 2' }} % <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="tax2_rate" id="editTax2Rate" class="form-control"
                                       min="0" max="100" step="0.01" required>
                                <span class="input-group-text">%</span>
                            </div>
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
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Tax Code</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete tax code <strong id="deleteLabel"></strong>? This cannot be undone.</p>
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
            fetch('{{ route("masters.tax-codes.reorder") }}', {
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
        document.getElementById('editTax1Rate').value    = btn.dataset.tax1_rate;
        document.getElementById('editTax2Rate').value    = btn.dataset.tax2_rate;
        document.getElementById('editForm').action =
            '{{ url("masters/tax-codes") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/tax-codes") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
