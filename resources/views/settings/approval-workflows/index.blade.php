@extends('layouts.app')

@section('title', 'Approval Workflows')

@section('breadcrumb')
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item">Configuration</li>
    <li class="breadcrumb-item active">Approval Workflows</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-diagram-3 me-2 text-primary"></i>Approval Workflows</h4>
        <p class="text-muted mb-0 small">Define approval steps for each document type. Steps are executed in order.</p>
    </div>
    @can('settings.approval-workflows.create')
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStepModal">
        <i class="bi bi-plus-circle me-1"></i>Add Step
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

@forelse($steps as $docType => $docSteps)
@php $docLabel = $docTypeLabels[$docType] ?? ucwords(str_replace('_', ' ', $docType)); @endphp
<div class="card content-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-check text-primary"></i>
            <span class="fw-semibold">{{ $docLabel }}</span>
            <code class="small text-muted">{{ $docType }}</code>
        </div>
        <span class="badge bg-secondary-subtle text-secondary">{{ $docSteps->count() }} {{ Str::plural('step', $docSteps->count()) }}</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px;" class="text-center ps-3">Order</th>
                    <th>Step Label</th>
                    <th style="width:160px;"><code class="small">step_key</code></th>
                    <th style="width:180px;">Required Role</th>
                    <th style="width:110px;" class="text-center">Auto-Approve</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:80px;" class="text-end pe-3">Edit</th>
                </tr>
            </thead>
            <tbody>
            @foreach($docSteps as $step)
            <tr class="{{ $step->is_active ? '' : 'table-secondary text-muted' }}">
                <td class="text-center ps-3">
                    <span class="badge bg-primary-subtle text-primary fw-semibold">{{ $step->step_order }}</span>
                </td>
                <td class="fw-semibold small">{{ $step->step_label }}</td>
                <td><code class="small">{{ $step->step_key }}</code></td>
                <td class="small">
                    @if($step->required_role)
                        <span class="badge bg-info-subtle text-info-emphasis">{{ $roles[$step->required_role] ?? $step->required_role }}</span>
                    @else
                        <span class="text-muted">Any authenticated user</span>
                    @endif
                </td>
                <td class="text-center">
                    @if($step->auto_approve_on_create)
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Yes</span>
                    @else
                        <span class="text-muted small">—</span>
                    @endif
                </td>
                <td class="text-center">
                    @can('settings.approval-workflows.edit')
                    <form method="POST" action="{{ route('settings.approval-workflows.toggle', $step) }}">
                        @csrf @method('PATCH')
                        <button type="submit"
                                class="btn btn-sm {{ $step->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                title="{{ $step->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                            <i class="bi {{ $step->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                        </button>
                    </form>
                    @endcan
                </td>
                <td class="text-end pe-3">
                    @can('settings.approval-workflows.edit')
                    <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                            data-id="{{ $step->id }}"
                            data-label="{{ $step->step_label }}"
                            data-role="{{ $step->required_role ?? '' }}"
                            data-auto="{{ $step->auto_approve_on_create ? '1' : '0' }}"
                            title="Edit step">
                        <i class="bi bi-pencil"></i>
                    </button>
                    @endcan
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="card content-card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-diagram-3 fs-1"></i>
        <p class="mt-3 mb-0">No workflow steps defined yet. Click <strong>Add Step</strong> to create one.</p>
    </div>
</div>
@endforelse

{{-- ── Add Step Modal ─────────────────────────────────────────────────────── --}}
<div class="modal fade" id="addStepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add Workflow Step</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('settings.approval-workflows.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <option value="">— Select —</option>
                            @foreach($docTypeLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">The step will be appended as the last step for the selected type.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Step Label <span class="text-danger">*</span></label>
                        <input type="text" name="step_label" class="form-control" required maxlength="100"
                               placeholder="e.g. Manager Sign-off">
                        <div class="form-text">Shown in the approval panel and on printed documents.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Required Role</label>
                        <select name="required_role" class="form-select">
                            @foreach($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Only users with this role can action this step. Leave blank for any user.</div>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="auto_approve_on_create"
                               id="addAutoApprove" value="1">
                        <label class="form-check-label fw-semibold" for="addAutoApprove">Auto-approve on submission</label>
                    </div>
                    <div class="form-text ms-4 mb-2">Step is automatically marked approved when the request is created (e.g. for the submitting user's own step).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('settings.approval-workflows.create')
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Step</button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Step Modal ────────────────────────────────────────────────────── --}}
<div class="modal fade" id="editStepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Workflow Step</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="editStepForm">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Step Label <span class="text-danger">*</span></label>
                        <input type="text" name="step_label" id="editLabel" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Required Role</label>
                        <select name="required_role" id="editRole" class="form-select">
                            @foreach($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" name="auto_approve_on_create"
                               id="editAutoApprove" value="1">
                        <label class="form-check-label fw-semibold" for="editAutoApprove">Auto-approve on submission</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('settings.approval-workflows.edit')
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        const id       = btn.dataset.id;
        const label    = btn.dataset.label;
        const role     = btn.dataset.role;
        const autoApp  = btn.dataset.auto === '1';

        document.getElementById('editLabel').value    = label;
        document.getElementById('editRole').value     = role;
        document.getElementById('editAutoApprove').checked = autoApp;
        document.getElementById('editStepForm').action =
            '{{ url("settings/approval-workflows") }}/' + id;

        new bootstrap.Modal(document.getElementById('editStepModal')).show();
    });
});
</script>
@endpush
