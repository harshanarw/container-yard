@extends('layouts.app')

@section('title', 'Banks')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Invoice</li>
    <li class="breadcrumb-item active">Banks</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-bank me-2 text-primary"></i>Banks</h4>
        <p class="text-muted mb-0 small">Maintain the list of banks used when creating bank accounts. Drag rows to reorder.</p>
    </div>
    <div class="d-flex gap-2">
        @can('masters.banks.view')
        <a href="{{ route('masters.banks.export') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>Export CSV
        </a>
        @endcan
        @can('masters.banks.create')
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-upload me-1"></i>Import CSV
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-1"></i>Add Bank
        </button>
        @endcan
    </div>
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

<div class="card content-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" id="bankTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th style="width:110px;">Short</th>
                    <th>Bank Name</th>
                    <th style="width:120px;">SWIFT</th>
                    <th style="width:120px;">Local Code</th>
                    <th style="width:180px;">Country</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:120px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody id="sortableBody">
            @forelse($banks as $bank)
                <tr data-id="{{ $bank->id }}"
                    class="{{ $bank->is_active ? '' : 'table-secondary text-muted' }}">
                    <td class="ps-3 drag-handle" style="cursor:grab;" title="Drag to reorder">
                        <i class="bi bi-grip-vertical text-muted"></i>
                    </td>
                    <td class="small text-muted fw-semibold">{{ $bank->sort_order }}</td>
                    <td>
                        @if($bank->short_name)
                            <span class="badge bg-primary" style="font-size:.75rem;">{{ $bank->short_name }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><span class="fw-semibold">{{ $bank->name }}</span></td>
                    <td class="small font-monospace text-muted">{{ $bank->swift_code ?: '—' }}</td>
                    <td class="small font-monospace text-muted">{{ $bank->local_code ?: '—' }}</td>
                    <td class="small text-muted">
                        @if($bank->countryInfo)
                            {{ $bank->countryInfo->flag_emoji }} {{ $bank->countryInfo->name }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center">
                        @can('masters.banks.edit')
                        <form method="POST" action="{{ route('masters.banks.toggle', $bank) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="btn btn-sm {{ $bank->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                    title="{{ $bank->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                <i class="bi {{ $bank->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        @else
                            <span class="badge {{ $bank->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $bank->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        @endcan
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            @can('masters.banks.edit')
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                    data-id="{{ $bank->id }}"
                                    data-name="{{ $bank->name }}"
                                    data-short_name="{{ $bank->short_name ?? '' }}"
                                    data-swift_code="{{ $bank->swift_code ?? '' }}"
                                    data-local_code="{{ $bank->local_code ?? '' }}"
                                    data-country_id="{{ $bank->country_id ?? '' }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @endcan
                            @can('masters.banks.delete')
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    data-id="{{ $bank->id }}"
                                    data-label="{{ $bank->name }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-bank fs-3 d-block mb-2"></i>
                        No banks yet. Click <strong>Add Bank</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $banks->count() }} total · {{ $banks->where('is_active', true)->count() }} active
        </span>
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.banks.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Bank</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   maxlength="150" required placeholder="e.g. Hatton National Bank PLC">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Short Name</label>
                            <input type="text" name="short_name" class="form-control"
                                   maxlength="50" placeholder="e.g. HNB">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SWIFT / BIC</label>
                            <input type="text" name="swift_code" class="form-control text-uppercase"
                                   maxlength="20" placeholder="e.g. HBLILKLX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Local Code</label>
                            <input type="text" name="local_code" class="form-control"
                                   maxlength="20" placeholder="e.g. CBSL / IFSC / Sort">
                            <div class="form-text">National clearing / routing code</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Country</label>
                            <select name="country_id" class="form-select select2-modal-add">
                                <option value="">— Select Country —</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c->id }}"
                                        {{ $defaultCountryId == $c->id ? 'selected' : '' }}>
                                        {{ $c->flag_emoji }} {{ $c->name }} ({{ $c->iso2 }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.banks.create')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add
                    </button>
                    @endcan
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
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Bank</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Short Name</label>
                            <input type="text" name="short_name" id="editShortName" class="form-control" maxlength="50">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">SWIFT / BIC</label>
                            <input type="text" name="swift_code" id="editSwiftCode" class="form-control text-uppercase" maxlength="20">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Local Code</label>
                            <input type="text" name="local_code" id="editLocalCode" class="form-control" maxlength="20">
                            <div class="form-text">National clearing / routing code</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Country</label>
                            <select name="country_id" id="editCountryId" class="form-select select2-modal-edit">
                                <option value="">— Select Country —</option>
                                @foreach($countries as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->flag_emoji }} {{ $c->name }} ({{ $c->iso2 }})
                                    </option>
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
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Bank</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete bank <strong id="deleteLabel"></strong>? This cannot be undone.</p>
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

{{-- ── Import Modal ── --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.banks.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-upload me-1 text-primary"></i>Import Banks (CSV)</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                    </div>
                    <div class="small text-muted">
                        <p class="mb-1">First row must be a header. Recognised columns:</p>
                        <code class="d-block mb-2">name, short_name, swift_code, local_code, country_iso</code>
                        <p class="mb-1"><strong>name</strong> is required; other columns are optional.
                        Rows without <strong>country_iso</strong> are assigned to this deployment's country.</p>
                        <p class="mb-0">Existing banks (same name + country) are updated; new ones are added.
                        Tip: <a href="{{ route('masters.banks.export') }}">export the current list</a> to use as a template.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Import</button>
                </div>
            </form>
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
            fetch('{{ route("masters.banks.reorder") }}', {
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

// ── Select2 in modals ────────────────────────────────────────────────────────
$('#addModal').on('shown.bs.modal', function () {
    $(this).find('.select2-modal-add').select2({ theme: 'bootstrap-5', dropdownParent: $(this) });
});
$('#editModal').on('shown.bs.modal', function () {
    $(this).find('.select2-modal-edit').select2({ theme: 'bootstrap-5', dropdownParent: $(this) });
});

// ── Edit modal ───────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editName').value      = btn.dataset.name;
        document.getElementById('editShortName').value = btn.dataset.short_name;
        document.getElementById('editSwiftCode').value = btn.dataset.swift_code;
        document.getElementById('editLocalCode').value = btn.dataset.local_code;
        document.getElementById('editCountryId').value = btn.dataset.country_id || '';
        document.getElementById('editForm').action     =
            '{{ url("masters/banks") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/banks") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
