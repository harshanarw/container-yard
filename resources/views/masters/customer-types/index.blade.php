@extends('layouts.app')

@section('title', 'Customer Types')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Customer</li>
    <li class="breadcrumb-item active">Customer Types</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tags me-2 text-primary"></i>Customer Types</h4>
        <p class="text-muted mb-0 small">Define customer role types that can be tagged to customer profiles. Drag rows to reorder.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Customer Type
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
        <table class="table table-hover align-middle mb-0" id="ctTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th style="width:100px;" class="text-center">Customers</th>
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
                    <td class="fw-semibold small">{{ $item->name }}</td>
                    <td class="small text-muted">{{ $item->description ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-primary rounded-pill">{{ $item->customers_count ?? 0 }}</span>
                    </td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('masters.customer-types.toggle', $item) }}">
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
                                    data-name="{{ $item->name }}"
                                    data-description="{{ $item->description }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-delete"
                                    data-id="{{ $item->id }}"
                                    data-label="{{ $item->name }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-tags fs-3 d-block mb-2"></i>
                        No customer types yet. Click <strong>Add Customer Type</strong> to get started.
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
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.customer-types.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Customer Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control"
                               maxlength="100" required placeholder="e.g. Shipping Line">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control"
                               maxlength="200" placeholder="Optional description">
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
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Customer Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control"
                               maxlength="100" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" id="editDescription" class="form-control"
                               maxlength="200">
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
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete customer type <strong id="deleteLabel"></strong>?
                   Customers tagged with this type will lose the association.</p>
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
const tbody = document.getElementById('sortableBody');
if (tbody) {
    Sortable.create(tbody, {
        handle: '.drag-handle',
        animation: 150,
        onEnd() {
            const order = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
            fetch('{{ route("masters.customer-types.reorder") }}', {
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

document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editName').value        = btn.dataset.name;
        document.getElementById('editDescription').value = btn.dataset.description ?? '';
        document.getElementById('editForm').action =
            '{{ url("masters/customer-types") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/customer-types") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
