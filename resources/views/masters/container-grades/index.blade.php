@extends('layouts.app')

@section('title', 'Container Grades')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item active">Container Grades</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-award me-2 text-primary"></i>Container Grades</h4>
        <p class="text-muted mb-0 small">Manage grade classifications for cargo suitability (e.g. Fiber Grade, Tea Grade). Drag to reorder.</p>
    </div>
    @can('masters.container-grades.create')
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Grade
    </button>
    @endcan
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
        <table class="table table-hover align-middle mb-0" id="gradeTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th style="width:80px;">Code</th>
                    <th>Name</th>
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
                        <span class="badge bg-{{ $item->color ?? 'secondary' }} fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $item->code }}
                        </span>
                    </td>
                    <td class="fw-semibold small">{{ $item->name }}</td>
                    <td class="small text-muted">{{ $item->description ?? '—' }}</td>
                    <td class="text-center">
                        @can('masters.container-grades.edit')
                        <form method="POST" action="{{ route('masters.container-grades.toggle', $item) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="btn btn-sm {{ $item->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                    title="{{ $item->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                <i class="bi {{ $item->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            @can('masters.container-grades.edit')
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                    data-id="{{ $item->id }}"
                                    data-code="{{ $item->code }}"
                                    data-name="{{ $item->name }}"
                                    data-description="{{ $item->description }}"
                                    data-color="{{ $item->color }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @endcan
                            @can('masters.container-grades.delete')
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    data-id="{{ $item->id }}"
                                    data-label="{{ $item->code }} — {{ $item->name }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-award fs-3 d-block mb-2"></i>
                        No grades yet. Click <strong>Add Grade</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">{{ $items->count() }} grade(s) total · {{ $items->where('is_active', true)->count() }} active</span>
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.container-grades.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Container Grade</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase"
                                   maxlength="10" required placeholder="e.g. G1">
                            <div class="form-text">Short identifier (max 10 chars)</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   maxlength="100" required placeholder="e.g. Fiber Grade">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2"
                                      maxlength="500" placeholder="Brief description of what this grade means…"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Badge Colour <span class="text-danger">*</span></label>
                            <select name="color" class="form-select" required id="addColorSelect">
                                <option value="success">Green — Success</option>
                                <option value="primary">Blue — Primary</option>
                                <option value="info">Teal — Info</option>
                                <option value="secondary" selected>Grey — Secondary</option>
                                <option value="warning">Yellow — Warning</option>
                                <option value="danger">Red — Danger</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end pb-1">
                            <span class="text-muted small me-2">Preview:</span>
                            <span class="badge fw-bold" id="addColorPreview" style="font-size:.85rem;">G?</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.container-grades.create')
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
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Container Grade</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode" class="form-control text-uppercase"
                                   maxlength="10" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control"
                                   maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="2"
                                      maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Badge Colour <span class="text-danger">*</span></label>
                            <select name="color" id="editColorSelect" class="form-select" required>
                                <option value="success">Green — Success</option>
                                <option value="primary">Blue — Primary</option>
                                <option value="info">Teal — Info</option>
                                <option value="secondary">Grey — Secondary</option>
                                <option value="warning">Yellow — Warning</option>
                                <option value="danger">Red — Danger</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end pb-1">
                            <span class="text-muted small me-2">Preview:</span>
                            <span class="badge fw-bold" id="editColorPreview" style="font-size:.85rem;">G?</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.container-grades.edit')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                    @endcan
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
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Grade</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete grade <strong id="deleteLabel"></strong>?
                   This cannot be undone. Grades in use by containers or gate movements cannot be deleted.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                @can('masters.container-grades.delete')
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
                @endcan
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
// ── Badge colour live preview ────────────────────────────────────────────────
function applyColorPreview(selectEl, previewEl) {
    const colorMap = {
        success: 'bg-success', primary: 'bg-primary', info: 'bg-info text-dark',
        secondary: 'bg-secondary', warning: 'bg-warning text-dark', danger: 'bg-danger',
    };
    previewEl.className = 'badge fw-bold ' + (colorMap[selectEl.value] || 'bg-secondary');
}

const addSel  = document.getElementById('addColorSelect');
const addPrev = document.getElementById('addColorPreview');
addSel.addEventListener('change', () => applyColorPreview(addSel, addPrev));
applyColorPreview(addSel, addPrev);

const editSel  = document.getElementById('editColorSelect');
const editPrev = document.getElementById('editColorPreview');
editSel.addEventListener('change', () => applyColorPreview(editSel, editPrev));

// Keep add-modal code preview in sync with the code field
document.querySelector('#addModal input[name="code"]').addEventListener('input', function () {
    addPrev.textContent = this.value.toUpperCase() || 'G?';
});
document.getElementById('editCode').addEventListener('input', function () {
    editPrev.textContent = this.value.toUpperCase() || 'G?';
});

// ── Drag-to-reorder ──────────────────────────────────────────────────────────
const tbody = document.getElementById('sortableBody');
if (tbody) {
    Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd() {
            const order = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
            fetch('{{ route("masters.container-grades.reorder") }}', {
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
        document.getElementById('editName').value        = btn.dataset.name;
        document.getElementById('editDescription').value = btn.dataset.description ?? '';
        editSel.value = btn.dataset.color ?? 'secondary';
        editPrev.textContent = btn.dataset.code;
        applyColorPreview(editSel, editPrev);
        document.getElementById('editForm').action =
            '{{ url("masters/container-grades") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/container-grades") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
