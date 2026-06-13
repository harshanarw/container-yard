@extends('layouts.app')

@section('title', 'Gate-In Job Types')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('masters.zones.index') }}">Setup</a></li>
    <li class="breadcrumb-item active">Gate-In Job Types</li>
@endsection

@section('content')

{{-- ── Page header ─────────────────────────────────────────────────────────── --}}
<div class="d-flex justify-content-between align-items-center page-header mb-3">
    <div>
        <h4><i class="bi bi-signpost-split me-2"></i>Gate-In Job Types</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb">@yield('breadcrumb')</ol></nav>
    </div>
    @can('masters.job-types.create')
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>New Job Type
    </button>
    @endcan
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- ── Info banner ──────────────────────────────────────────────────────────── --}}
<div class="alert alert-info small py-2 mb-3">
    <i class="bi bi-info-circle me-1"></i>
    Job types classify each gate-in transaction by its operational purpose. Enabled flags determine which billing streams and workflows apply.
    <strong>System types</strong> (padlock icon) have protected codes but their flags and descriptions may be edited.
</div>

{{-- ── Main card ────────────────────────────────────────────────────────────── --}}
<div class="card content-card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold"><i class="bi bi-signpost-split me-1 text-primary"></i>Job Type Master</span>
        <span class="text-muted small">{{ $jobTypes->count() }} types &nbsp;|&nbsp; {{ $jobTypes->where('is_active', true)->count() }} active</span>
    </div>
    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="ps-3" style="width:36px;"></th>
                <th style="width:36px;">#</th>
                <th style="width:200px;">Code / Short</th>
                <th>Name / Description</th>
                <th style="width:80px;" class="text-center">Direction</th>
                <th>Applicable Workflows</th>
                <th style="width:80px;" class="text-center">Status</th>
                <th style="width:90px;" class="text-end pe-3">Actions</th>
            </tr>
        </thead>
        <tbody id="sortableBody">
        @forelse($jobTypes as $jt)
        <tr data-id="{{ $jt->id }}" class="{{ $jt->is_active ? '' : 'table-secondary text-muted' }}">
            {{-- drag handle --}}
            <td class="ps-3 drag-handle text-muted" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></td>
            {{-- sort order --}}
            <td class="small text-muted fw-semibold">{{ $jt->sort_order }}</td>
            {{-- code + short code --}}
            <td>
                <div>
                    <span class="badge bg-primary font-monospace" style="font-size:.72rem;">{{ $jt->job_type_code }}</span>
                    @if($jt->is_system)
                        <i class="bi bi-lock-fill text-secondary ms-1" title="System type" style="font-size:.65rem;"></i>
                    @endif
                </div>
                @if($jt->type_short_code)
                <div class="mt-1">
                    <span class="badge bg-info-subtle text-info border font-monospace" style="font-size:.72rem;" title="Short code for Job Number">{{ $jt->type_short_code }}</span>
                </div>
                @endif
            </td>
            {{-- name + description --}}
            <td>
                <div class="fw-semibold small">{{ $jt->job_type_name }}</div>
                @if($jt->description)
                <div class="text-muted" style="font-size:.72rem;">{{ Str::limit($jt->description, 90) }}</div>
                @endif
                @if($jt->default_next_status)
                <span class="badge bg-light text-secondary border mt-1" style="font-size:.65rem;">
                    <i class="bi bi-arrow-right me-1"></i>{{ str_replace('_', ' ', $jt->default_next_status) }}
                </span>
                @endif
            </td>
            {{-- direction --}}
            <td class="text-center">
                <span class="badge {{ $jt->directionBadge($jt->movement_direction) }}" style="font-size:.68rem;">
                    {{ $jt->movement_direction === 'gate_in' ? 'Gate In' : 'Gate Out' }}
                </span>
            </td>
            {{-- workflow flags --}}
            <td>
                <div class="d-flex flex-wrap gap-1">
                @foreach($flags as $col => $label)
                    @if($jt->$col)
                    <span class="badge bg-primary-subtle text-primary border" style="font-size:.65rem;">{{ $label }}</span>
                    @endif
                @endforeach
                @if($jt->approval_required)
                    <span class="badge bg-warning-subtle text-warning border" style="font-size:.65rem;"><i class="bi bi-shield-check me-1"></i>Approval</span>
                @endif
                @if($jt->damage_capture_required)
                    <span class="badge bg-danger-subtle text-danger border" style="font-size:.65rem;"><i class="bi bi-exclamation-triangle me-1"></i>Damage Cap.</span>
                @endif
                </div>
            </td>
            {{-- status toggle --}}
            <td class="text-center">
                @can('masters.job-types.edit')
                <form method="POST" action="{{ route('masters.job-types.toggle', $jt) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm {{ $jt->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ $jt->is_active ? 'Deactivate' : 'Activate' }}">
                        <i class="bi {{ $jt->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                    </button>
                </form>
                @else
                    <span class="badge {{ $jt->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $jt->is_active ? 'Active' : 'Inactive' }}</span>
                @endcan
            </td>
            {{-- actions --}}
            <td class="text-end pe-3">
                @can('masters.job-types.edit')
                <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                        data-id="{{ $jt->id }}"
                        data-code="{{ $jt->job_type_code }}"
                        data-short-code="{{ $jt->type_short_code }}"
                        data-name="{{ $jt->job_type_name }}"
                        data-direction="{{ $jt->movement_direction }}"
                        data-description="{{ $jt->description }}"
                        data-next-status="{{ $jt->default_next_status }}"
                        data-remarks="{{ $jt->remarks }}"
                        data-system="{{ $jt->is_system ? '1' : '0' }}"
                        data-flags="{{ json_encode(array_keys(array_filter(array_map(fn($col) => (bool)$jt->$col, array_combine(array_keys($flags), array_keys($flags)))))) }}"
                        data-approval="{{ $jt->approval_required ? '1' : '0' }}"
                        data-damage="{{ $jt->damage_capture_required ? '1' : '0' }}"
                        title="Edit">
                    <i class="bi bi-pencil"></i>
                </button>
                @endcan
                @can('masters.job-types.delete')
                @if(!$jt->is_system)
                <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                        data-id="{{ $jt->id }}"
                        data-code="{{ $jt->job_type_code }}"
                        title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
                @endif
                @endcan
            </td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center text-muted py-5">No job types defined yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
    <div class="card-footer bg-light py-2 small text-muted">
        Drag rows to reorder. System types (padlock) are seeded defaults — their codes are protected.
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════
     ADD MODAL
════════════════════════════════════════════════════════════ --}}
@can('masters.job-types.create')
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.job-types.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-semibold"><i class="bi bi-plus-circle me-1 text-primary"></i>New Gate-In Job Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="job_type_code" class="form-control text-uppercase font-monospace"
                                   maxlength="30" required placeholder="e.g. REPAIR_IN"
                                   pattern="[A-Z0-9_]+" title="Uppercase letters, digits and underscores only">
                            <div class="form-text">UPPERCASE_WITH_UNDERSCORES</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Short Code <span class="text-danger">*</span></label>
                            <input type="text" name="type_short_code" class="form-control text-uppercase font-monospace"
                                   maxlength="5" required placeholder="e.g. RP"
                                   pattern="[A-Z]{2,5}" title="2–5 uppercase letters, used in Job Number">
                            <div class="form-text">2–5 uppercase letters</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="job_type_name" class="form-control" maxlength="100" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Direction <span class="text-danger">*</span></label>
                            <select name="movement_direction" class="form-select" required>
                                <option value="gate_in" selected>Gate In</option>
                                <option value="gate_out">Gate Out</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Default Next Status</label>
                            <input type="text" name="default_next_status" class="form-control font-monospace"
                                   maxlength="50" placeholder="e.g. pending_survey">
                            <div class="form-text">Snake_case hint shown in yard list.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Remarks</label>
                            <input type="text" name="remarks" class="form-control" maxlength="500">
                        </div>
                    </div>

                    {{-- Workflow / Revenue Flags --}}
                    <div class="card border shadow-none">
                        <div class="card-header py-2 bg-light">
                            <span class="fw-semibold small text-uppercase text-secondary"><i class="bi bi-toggles me-1"></i>Workflow &amp; Revenue Flags</span>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-2">
                                @foreach($flags as $col => $label)
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="{{ $col }}" value="1" id="add_{{ $col }}">
                                        <label class="form-check-label small" for="add_{{ $col }}">{{ $label }}</label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <hr class="my-2">
                            <div class="row g-2">
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="approval_required" value="1" id="add_approval_required">
                                        <label class="form-check-label small fw-semibold text-warning" for="add_approval_required"><i class="bi bi-shield-check me-1"></i>Approval Required</label>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="damage_capture_required" value="1" id="add_damage_capture_required">
                                        <label class="form-check-label small fw-semibold text-danger" for="add_damage_capture_required"><i class="bi bi-exclamation-triangle me-1"></i>Damage Capture Required</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Create Job Type</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

{{-- ════════════════════════════════════════════════════════════
     EDIT MODAL
════════════════════════════════════════════════════════════ --}}
@can('masters.job-types.edit')
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title fw-semibold"><i class="bi bi-pencil me-1 text-primary"></i>Edit Job Type</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="job_type_code" id="edit_code" class="form-control text-uppercase font-monospace"
                                   maxlength="30" required pattern="[A-Z0-9_]+">
                            <div class="form-text" id="edit_code_hint"></div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Short Code <span class="text-danger">*</span></label>
                            <input type="text" name="type_short_code" id="edit_short_code" class="form-control text-uppercase font-monospace"
                                   maxlength="5" required pattern="[A-Z]{2,5}" title="2–5 uppercase letters">
                            <div class="form-text">Job Number prefix</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" name="job_type_name" id="edit_name" class="form-control" maxlength="100" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Direction <span class="text-danger">*</span></label>
                            <select name="movement_direction" id="edit_direction" class="form-select" required>
                                <option value="gate_in">Gate In</option>
                                <option value="gate_out">Gate Out</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Default Next Status</label>
                            <input type="text" name="default_next_status" id="edit_next_status" class="form-control font-monospace" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Remarks</label>
                            <input type="text" name="remarks" id="edit_remarks" class="form-control" maxlength="500">
                        </div>
                    </div>

                    {{-- Workflow / Revenue Flags --}}
                    <div class="card border shadow-none">
                        <div class="card-header py-2 bg-light">
                            <span class="fw-semibold small text-uppercase text-secondary"><i class="bi bi-toggles me-1"></i>Workflow &amp; Revenue Flags</span>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-2">
                                @foreach($flags as $col => $label)
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input edit-flag" type="checkbox" name="{{ $col }}" value="1"
                                               id="edit_{{ $col }}" data-flag="{{ $col }}">
                                        <label class="form-check-label small" for="edit_{{ $col }}">{{ $label }}</label>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <hr class="my-2">
                            <div class="row g-2">
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="approval_required" value="1" id="edit_approval_required">
                                        <label class="form-check-label small fw-semibold text-warning" for="edit_approval_required"><i class="bi bi-shield-check me-1"></i>Approval Required</label>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="damage_capture_required" value="1" id="edit_damage_capture_required">
                                        <label class="form-check-label small fw-semibold text-danger" for="edit_damage_capture_required"><i class="bi bi-exclamation-triangle me-1"></i>Damage Capture Required</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

{{-- ════════════════════════════════════════════════════════════
     DELETE MODALS (one per system-unprotected row, rendered inline)
════════════════════════════════════════════════════════════ --}}
@can('masters.job-types.delete')
@foreach($jobTypes->where('is_system', false) as $jt)
<form method="POST" id="deleteForm-{{ $jt->id }}" action="{{ route('masters.job-types.destroy', $jt) }}" style="display:none;">
    @csrf @method('DELETE')
</form>
@endforeach
@endcan

@endsection

@push('scripts')
{{-- SortableJS CDN --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    // ── Drag-to-reorder ────────────────────────────────────────────────────────
    const tbody = document.getElementById('sortableBody');
    if (tbody) {
        Sortable.create(tbody, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function () {
                const order = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
                fetch('{{ route('masters.job-types.reorder') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({ order }),
                });
            },
        });
    }

    // ── Edit modal ─────────────────────────────────────────────────────────────
    const editModal = document.getElementById('editModal');
    const allFlags  = @json(array_keys($flags));

    document.querySelectorAll('.btn-edit').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;

            document.getElementById('editForm').action =
                '/masters/job-types/' + d.id;

            document.getElementById('edit_code').value        = d.code;
            document.getElementById('edit_short_code').value  = d.shortCode || '';
            document.getElementById('edit_name').value        = d.name;
            document.getElementById('edit_description').value = d.description || '';
            document.getElementById('edit_next_status').value = d.nextStatus || '';
            document.getElementById('edit_remarks').value     = d.remarks || '';
            document.getElementById('edit_direction').value   = d.direction;

            // System types: lock code & direction
            const isSystem = d.system === '1';
            document.getElementById('edit_code').readOnly      = isSystem;
            document.getElementById('edit_direction').disabled = isSystem;
            document.getElementById('edit_code_hint').textContent =
                isSystem ? 'System type — code is protected.' : '';

            // Apply flag checkboxes
            const activeFlags = JSON.parse(d.flags || '[]');
            allFlags.forEach(flag => {
                const cb = document.getElementById('edit_' + flag);
                if (cb) cb.checked = activeFlags.includes(flag);
            });

            document.getElementById('edit_approval_required').checked      = d.approval === '1';
            document.getElementById('edit_damage_capture_required').checked = d.damage   === '1';

            new bootstrap.Modal(editModal).show();
        });
    });

    // ── Delete confirm ─────────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function () {
            const code = this.dataset.code;
            const id   = this.dataset.id;
            confirmAction(
                `Delete job type "${code}"? This cannot be undone.`,
                () => document.getElementById('deleteForm-' + id)?.submit(),
                { title: 'Delete Job Type', confirmClass: 'btn-danger', confirmLabel: 'Delete' }
            );
        });
    });
})();
</script>
@endpush
