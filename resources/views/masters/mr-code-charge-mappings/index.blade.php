@extends('layouts.app')

@section('title', 'MR Code → Charge Code Mappings')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">M&amp;R</li>
    <li class="breadcrumb-item active">Charge Code Mappings</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>MR Code → Charge Code Mappings</h4>
        <p class="text-muted small mb-0">
            When a component or repair code is selected on an estimate line, the system looks up
            the best matching rule here and auto-fills the Charge Code (and its Tax Code).
        </p>
    </div>
    @can('masters.mr-codes.create')
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Rule
    </button>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2 small">
    @foreach($errors->all() as $e)<div><i class="bi bi-exclamation-circle me-1"></i>{{ $e }}</div>@endforeach
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Matching order:</strong>
    Component + Repair Code (score 3) wins over Component only (2) or Repair only (1).
    Within the same score the lowest <strong>Priority</strong> number wins.
    At least one of Component Code or Repair Code must be set per rule.
</div>

<div class="card content-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:70px;">Priority</th>
                    <th style="width:22%;">Component Code</th>
                    <th style="width:20%;">Repair Code</th>
                    <th>→ Charge Code</th>
                    <th style="width:18%;">Tax Code</th>
                    <th style="width:80px;" class="text-center">Status</th>
                    <th style="width:100px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($mappings as $mapping)
                <tr class="{{ $mapping->is_active ? '' : 'table-secondary text-muted' }}">
                    <td class="ps-3 text-center">
                        <span class="badge bg-light text-dark border fw-bold">{{ $mapping->priority }}</span>
                    </td>
                    <td class="small">
                        @if($mapping->componentCode)
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">{{ $mapping->componentCode->code }}</span>
                            <span class="ms-1">{{ $mapping->componentCode->name }}</span>
                        @else
                            <span class="text-muted fst-italic">Any</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($mapping->repairCode)
                            <span class="badge bg-secondary-subtle text-secondary border font-monospace">{{ $mapping->repairCode->code }}</span>
                            <span class="ms-1">{{ $mapping->repairCode->name }}</span>
                        @else
                            <span class="text-muted fst-italic">Any</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-warning-subtle text-warning border font-monospace fw-bold">{{ $mapping->chargeCode->code }}</span>
                        <span class="small ms-1">{{ $mapping->chargeCode->description }}</span>
                    </td>
                    <td class="small">
                        @if($mapping->chargeCode->taxCode)
                            <span class="badge bg-info-subtle text-info border border-info-subtle">
                                {{ $mapping->chargeCode->taxCode->code }}
                            </span>
                            <span class="text-muted ms-1">{{ number_format($mapping->chargeCode->taxCode->total_rate, 2) }}%</span>
                        @else
                            <span class="badge bg-light border text-muted">No Tax</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @can('masters.mr-codes.edit')
                        <form method="POST" action="{{ route('masters.mr-charge-mappings.toggle', $mapping) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $mapping->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                <i class="bi {{ $mapping->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            @can('masters.mr-codes.edit')
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                    data-id="{{ $mapping->id }}"
                                    data-component="{{ $mapping->component_code_id ?? '' }}"
                                    data-repair="{{ $mapping->repair_code_id ?? '' }}"
                                    data-charge="{{ $mapping->charge_code_id }}"
                                    data-priority="{{ $mapping->priority }}"
                                    data-notes="{{ $mapping->notes ?? '' }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            @endcan
                            @can('masters.mr-codes.delete')
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    data-id="{{ $mapping->id }}"
                                    data-label="{{ ($mapping->componentCode->code ?? 'Any') . ' / ' . ($mapping->repairCode->code ?? 'Any') . ' → ' . $mapping->chargeCode->code }}">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-arrow-left-right fs-3 d-block mb-2"></i>
                        No mapping rules yet. Click <strong>Add Rule</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2 small text-muted">
        {{ $mappings->count() }} rule(s) &middot;
        {{ $mappings->where('is_active', true)->count() }} active
    </div>
</div>

{{-- ── Add Modal ──────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.mr-charge-mappings.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Mapping Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-secondary py-2 small">
                        Set at least one of Component Code or Repair Code.
                        Leave a field as <em>Any</em> to match all values for that dimension.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Component Code</label>
                            <select name="component_code_id" class="form-select">
                                <option value="">— Any —</option>
                                @foreach($componentCodes as $c)
                                    <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Repair Code</label>
                            <select name="repair_code_id" class="form-select">
                                <option value="">— Any —</option>
                                @foreach($repairCodes as $r)
                                    <option value="{{ $r->id }}">{{ $r->code }} — {{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">→ Charge Code <span class="text-danger">*</span></label>
                            <select name="charge_code_id" class="form-select" required>
                                <option value="">— Select Charge Code —</option>
                                @foreach($chargeCodes as $cc)
                                    <option value="{{ $cc->id }}">
                                        {{ $cc->code }} — {{ $cc->description }}
                                        @if($cc->taxCode) ({{ $cc->taxCode->code }} {{ number_format($cc->taxCode->total_rate,2) }}%) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                            <input type="number" name="priority" class="form-control" value="10" min="1" max="999" required>
                            <div class="form-text">Lower number = higher priority</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Optional reference note">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.mr-codes.create')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Add Rule
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Edit Modal ─────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Mapping Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Component Code</label>
                            <select name="component_code_id" id="editComponent" class="form-select">
                                <option value="">— Any —</option>
                                @foreach($componentCodes as $c)
                                    <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Repair Code</label>
                            <select name="repair_code_id" id="editRepair" class="form-select">
                                <option value="">— Any —</option>
                                @foreach($repairCodes as $r)
                                    <option value="{{ $r->id }}">{{ $r->code }} — {{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">→ Charge Code <span class="text-danger">*</span></label>
                            <select name="charge_code_id" id="editCharge" class="form-select" required>
                                <option value="">— Select Charge Code —</option>
                                @foreach($chargeCodes as $cc)
                                    <option value="{{ $cc->id }}">
                                        {{ $cc->code }} — {{ $cc->description }}
                                        @if($cc->taxCode) ({{ $cc->taxCode->code }} {{ number_format($cc->taxCode->total_rate,2) }}%) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                            <input type="number" name="priority" id="editPriority" class="form-control" min="1" max="999" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" id="editNotes" class="form-control" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    @can('masters.mr-codes.edit')
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    @endcan
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Delete Modal ────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Rule</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete mapping <strong id="deleteLabel"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                @can('masters.mr-codes.delete')
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
<script>
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editComponent').value = btn.dataset.component || '';
        document.getElementById('editRepair').value    = btn.dataset.repair    || '';
        document.getElementById('editCharge').value    = btn.dataset.charge;
        document.getElementById('editPriority').value  = btn.dataset.priority;
        document.getElementById('editNotes').value     = btn.dataset.notes     || '';
        document.getElementById('editForm').action     = '{{ url("masters/mr-charge-mappings") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action = '{{ url("masters/mr-charge-mappings") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
