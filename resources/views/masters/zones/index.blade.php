@extends('layouts.app')

@section('title', 'Storage Zones')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item active">Storage Zones</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-map me-2 text-primary"></i>Storage Zones</h4>
        <p class="text-muted mb-0 small">Define yard zones that contain rows, bays, and tiers for container placement.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Zone
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
@if ($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Zone Table --}}
<div class="card content-card mb-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:50px;">Color</th>
                    <th style="width:90px;">Code</th>
                    <th style="width:160px;">Name</th>
                    <th>Description</th>
                    <th style="width:160px;" class="text-center">Slots</th>
                    <th style="width:90px;" class="text-center">Sort</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:120px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($zones as $zone)
                <tr class="{{ $zone->is_active ? '' : 'table-secondary text-muted' }}">
                    {{-- Color swatch --}}
                    <td class="ps-3">
                        <div style="width:28px;height:28px;border-radius:6px;background:{{ $zone->color ?? '#6b7280' }};border:1.5px solid rgba(0,0,0,.1);"
                             title="{{ $zone->color ?? '#6b7280' }}"></div>
                    </td>
                    {{-- Code --}}
                    <td>
                        <span class="badge fw-bold" style="background:{{ $zone->color ?? '#6b7280' }};font-size:.8rem;letter-spacing:.5px;">
                            {{ $zone->code }}
                        </span>
                    </td>
                    {{-- Name --}}
                    <td class="fw-semibold small">{{ $zone->name }}</td>
                    {{-- Description --}}
                    <td class="small text-muted">{{ $zone->description ?? '—' }}</td>
                    {{-- Slots --}}
                    <td class="text-center">
                        @if(isset($zone->yard_locations_count) && $zone->yard_locations_count > 0)
                        <div class="d-flex align-items-center justify-content-center gap-2">
                            <span class="badge bg-primary-subtle text-primary" title="Total">
                                {{ $zone->yard_locations_count }}
                            </span>
                            <span class="badge bg-success-subtle text-success" title="Empty">
                                <i class="bi bi-square me-1" style="font-size:.65rem;"></i>{{ $zone->empty_count ?? 0 }}
                            </span>
                            <span class="badge bg-info-subtle text-info" title="Occupied">
                                <i class="bi bi-box me-1" style="font-size:.65rem;"></i>{{ $zone->occupied_count ?? 0 }}
                            </span>
                        </div>
                        @else
                            <span class="text-muted small">No slots</span>
                        @endif
                    </td>
                    {{-- Sort Order --}}
                    <td class="text-center small text-muted">{{ $zone->sort_order }}</td>
                    {{-- Status badge --}}
                    <td class="text-center">
                        <span class="badge rounded-pill {{ $zone->is_active ? 'bg-success' : 'bg-secondary' }}">
                            {{ $zone->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    {{-- Actions --}}
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            {{-- Configure Slots --}}
                            <a href="{{ route('masters.zones.slots', $zone) }}"
                               class="btn btn-outline-info" title="Configure Slots">
                                <i class="bi bi-grid-3x3-gap"></i>
                            </a>
                            {{-- Edit --}}
                            <button type="button" class="btn btn-outline-primary btn-edit"
                                    data-id="{{ $zone->id }}"
                                    data-code="{{ $zone->code }}"
                                    data-name="{{ $zone->name }}"
                                    data-description="{{ $zone->description }}"
                                    data-color="{{ $zone->color ?? '#6b7280' }}"
                                    data-sort_order="{{ $zone->sort_order }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            {{-- Toggle active --}}
                            <form method="POST" action="{{ route('masters.zones.toggle', $zone) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm {{ $zone->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                        title="{{ $zone->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                    <i class="bi {{ $zone->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                            {{-- Delete --}}
                            @if(($zone->yard_locations_count ?? 0) > 0)
                                <button type="button" class="btn btn-outline-danger"
                                        disabled
                                        title="Cannot delete — zone has {{ $zone->yard_locations_count }} slot(s). Use Configure Slots to remove them first."
                                        data-bs-toggle="tooltip" data-bs-placement="left">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @else
                                <button type="button" class="btn btn-outline-danger btn-delete"
                                        data-id="{{ $zone->id }}"
                                        data-label="{{ $zone->code }} — {{ $zone->name }}"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-map fs-3 d-block mb-2"></i>
                        No zones defined yet. Add one below to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $zones->count() }} zone(s) total &middot;
            {{ $zones->where('is_active', true)->count() }} active &middot;
            {{ $zones->sum('yard_locations_count') }} total slots
        </span>
    </div>
</div>

{{-- Create Zone Card --}}
<div class="card content-card">
    <div class="card-header">
        <i class="bi bi-plus-circle me-2 text-primary"></i>Add New Zone
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('masters.zones.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control text-uppercase @error('code') is-invalid @enderror"
                           value="{{ old('code') }}" maxlength="10" required placeholder="e.g. A, B, NORTH">
                    <div class="form-text">Short unique identifier.</div>
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}" maxlength="100" required placeholder="e.g. Zone A — 20ft Section">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                           value="{{ old('description') }}" maxlength="255" placeholder="Optional description of this zone">
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-semibold">Color</label>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" name="color" class="form-control form-control-color @error('color') is-invalid @enderror"
                               value="{{ old('color', '#3b82f6') }}" title="Pick zone color" style="width:48px;height:38px;padding:2px;">
                    </div>
                    @error('color')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
                           value="{{ old('sort_order', $zones->count() + 1) }}" min="1" max="999" placeholder="1">
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Create Zone
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── Edit Modal ── --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Zone</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode" class="form-control text-uppercase"
                                   maxlength="10" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control" maxlength="100" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Description</label>
                            <input type="text" name="description" id="editDescription" class="form-control" maxlength="255">
                        </div>
                        <div class="col-md-2 d-flex flex-column">
                            <label class="form-label fw-semibold">Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="color" id="editColor" class="form-control form-control-color"
                                       style="width:48px;height:38px;padding:2px;" title="Pick zone color">
                                <span class="small text-muted" id="editColorHex">#3b82f6</span>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Sort Order</label>
                            <input type="number" name="sort_order" id="editSortOrder" class="form-control" min="1" max="999">
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

{{-- ── Delete Confirm Modal ── --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Zone</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete zone <strong id="deleteLabel"></strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Initialise tooltips ──────────────────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

// ── Edit modal ───────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        document.getElementById('editCode').value        = btn.dataset.code;
        document.getElementById('editName').value        = btn.dataset.name;
        document.getElementById('editDescription').value = btn.dataset.description ?? '';
        document.getElementById('editColor').value       = btn.dataset.color ?? '#6b7280';
        document.getElementById('editColorHex').textContent = btn.dataset.color ?? '#6b7280';
        document.getElementById('editSortOrder').value   = btn.dataset.sort_order;
        document.getElementById('editForm').action       = '/masters/zones/' + id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// Update hex label as user picks color
document.getElementById('editColor')?.addEventListener('input', function () {
    document.getElementById('editColorHex').textContent = this.value;
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action       = '/masters/zones/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
