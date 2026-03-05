@extends('layouts.app')

@section('title', 'Equipment Types')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item active">Equipment Types</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-box-seam me-2 text-primary"></i>Equipment Types</h4>
        <p class="text-muted mb-0 small">Manage container size/type combinations used across the system. Drag rows to reorder.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Equipment Type
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

<div class="card content-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0" id="eqtTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th style="width:90px;">EQT Code</th>
                    <th style="width:90px;">ISO Code</th>
                    <th style="width:60px;">Size</th>
                    <th style="width:60px;">Type</th>
                    <th style="width:100px;">Height</th>
                    <th>Description</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:100px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody id="sortableBody">
            @forelse($items as $item)
                <tr data-id="{{ $item->id }}" class="{{ $item->is_active ? '' : 'table-secondary text-muted' }}">
                    <td class="ps-3 drag-handle" style="cursor:grab;" title="Drag to reorder">
                        <i class="bi bi-grip-vertical text-muted"></i>
                    </td>
                    <td class="small text-muted fw-semibold">{{ $item->sort_order }}</td>
                    <td>
                        <span class="badge bg-primary fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $item->eqt_code }}
                        </span>
                    </td>
                    <td><code class="small">{{ $item->iso_code ?? '—' }}</code></td>
                    <td><span class="badge bg-light border text-dark">{{ $item->size }}'</span></td>
                    <td><span class="badge bg-info-subtle text-info">{{ $item->type_code }}</span></td>
                    <td>
                        @if($item->height === 'High Cube')
                            <span class="badge bg-warning-subtle text-warning small">High Cube</span>
                        @else
                            <span class="text-muted small">Standard</span>
                        @endif
                    </td>
                    <td class="small">{{ $item->description ?? '—' }}</td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('masters.equipment-types.toggle', $item) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="btn btn-sm {{ $item->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                    title="{{ $item->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                <i class="bi {{ $item->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-edit"
                                    data-id="{{ $item->id }}"
                                    data-eqt_code="{{ $item->eqt_code }}"
                                    data-iso_code="{{ $item->iso_code }}"
                                    data-size="{{ $item->size }}"
                                    data-type_code="{{ $item->type_code }}"
                                    data-height="{{ $item->height }}"
                                    data-description="{{ $item->description }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-delete"
                                    data-id="{{ $item->id }}"
                                    data-label="{{ $item->eqt_code }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center text-muted py-5">
                        <i class="bi bi-box-seam fs-3 d-block mb-2"></i>
                        No equipment types yet. Click <strong>Add Equipment Type</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">{{ $items->count() }} type(s) total · {{ $items->where('is_active', true)->count() }} active</span>
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.equipment-types.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Equipment Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">EQT Code <span class="text-danger">*</span></label>
                            <input type="text" name="eqt_code" class="form-control text-uppercase"
                                   maxlength="10" required placeholder="e.g. 20GP">
                            <div class="form-text">Short code like 20GP, 40HC</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ISO Code</label>
                            <input type="text" name="iso_code" class="form-control text-uppercase"
                                   maxlength="10" placeholder="e.g. 22G0">
                            <div class="form-text">ISO 6346 size-type code</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Size (ft) <span class="text-danger">*</span></label>
                            <select name="size" class="form-select" required>
                                <option value="">—</option>
                                <option value="20">20'</option>
                                <option value="40">40'</option>
                                <option value="45">45'</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type_code" class="form-select" required>
                                <option value="">—</option>
                                <option value="GP">GP</option>
                                <option value="HC">HC</option>
                                <option value="RF">RF</option>
                                <option value="RH">RH</option>
                                <option value="OT">OT</option>
                                <option value="FR">FR</option>
                                <option value="TK">TK</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Height <span class="text-danger">*</span></label>
                            <select name="height" class="form-select" required>
                                <option value="Standard">Standard</option>
                                <option value="High Cube">High Cube</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" name="description" class="form-control"
                                   maxlength="200" placeholder="e.g. 20' General Purpose Container">
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Equipment Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">EQT Code <span class="text-danger">*</span></label>
                            <input type="text" name="eqt_code" id="editEqtCode" class="form-control text-uppercase"
                                   maxlength="10" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ISO Code</label>
                            <input type="text" name="iso_code" id="editIsoCode" class="form-control text-uppercase"
                                   maxlength="10">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Size (ft) <span class="text-danger">*</span></label>
                            <select name="size" id="editSize" class="form-select" required>
                                <option value="20">20'</option>
                                <option value="40">40'</option>
                                <option value="45">45'</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                            <select name="type_code" id="editTypeCode" class="form-select" required>
                                <option value="GP">GP</option>
                                <option value="HC">HC</option>
                                <option value="RF">RF</option>
                                <option value="RH">RH</option>
                                <option value="OT">OT</option>
                                <option value="FR">FR</option>
                                <option value="TK">TK</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Height <span class="text-danger">*</span></label>
                            <select name="height" id="editHeight" class="form-select" required>
                                <option value="Standard">Standard</option>
                                <option value="High Cube">High Cube</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" name="description" id="editDescription" class="form-control"
                                   maxlength="200">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save
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
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete equipment type <strong id="deleteLabel"></strong>?
                   Containers and inquiries using this type will retain their size and type data.</p>
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
            fetch('{{ route("masters.equipment-types.reorder") }}', {
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
        document.getElementById('editEqtCode').value    = btn.dataset.eqt_code;
        document.getElementById('editIsoCode').value    = btn.dataset.iso_code ?? '';
        document.getElementById('editSize').value       = btn.dataset.size;
        document.getElementById('editTypeCode').value   = btn.dataset.type_code;
        document.getElementById('editHeight').value     = btn.dataset.height;
        document.getElementById('editDescription').value = btn.dataset.description ?? '';
        document.getElementById('editForm').action =
            '{{ url("masters/equipment-types") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/equipment-types") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
