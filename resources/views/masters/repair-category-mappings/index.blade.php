@extends('layouts.app')

@section('title', 'Category Mapping Rules')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">M&amp;R</li>
    <li class="breadcrumb-item"><a href="{{ route('masters.repair-categories.index') }}">Repair Categories</a></li>
    <li class="breadcrumb-item active">Mapping Rules</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-diagram-3 me-2 text-primary"></i>Category Mapping Rules</h4>
        <p class="text-muted small mb-0">
            Define how component codes and repair types map to repair categories.
            When creating estimate lines, the system auto-suggests the category based on these rules.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('masters.repair-categories.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-tags me-1"></i>Categories
        </a>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-1"></i>Add Rule
        </button>
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
@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2 small">
    @foreach($errors->all() as $e)<div><i class="bi bi-exclamation-circle me-1"></i>{{ $e }}</div>@endforeach
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="alert alert-info py-2 small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Matching priority:</strong>
            Rules with <em>both</em> Component Code + Repair Type match first (score 3),
            then Component Code only (score 2), then Repair Type only (score 1).
            Within the same score, the rule with the lowest <strong>Priority</strong> number wins.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;" class="ps-3 text-center">Priority</th>
                    <th>Component Code</th>
                    <th>Repair Type</th>
                    <th>→ Category</th>
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
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                {{ $mapping->componentCode->code }}
                            </span>
                            {{ $mapping->componentCode->name }}
                        @else
                            <span class="text-muted fst-italic">Any</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($mapping->repair_type)
                            <span class="badge bg-secondary-subtle text-secondary border">
                                {{ ucfirst(str_replace('_', ' ', $mapping->repair_type)) }}
                            </span>
                        @else
                            <span class="text-muted fst-italic">Any</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-{{ $mapping->repairCategory->color }}">
                            {{ $mapping->repairCategory->code }}
                        </span>
                        <span class="small ms-1">{{ $mapping->repairCategory->name }}</span>
                    </td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('masters.repair-category-mappings.toggle', $mapping) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $mapping->is_active ? 'btn-success' : 'btn-outline-secondary' }}">
                                <i class="bi {{ $mapping->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                    data-id="{{ $mapping->id }}"
                                    data-category="{{ $mapping->repair_category_id }}"
                                    data-component="{{ $mapping->component_code_id }}"
                                    data-repair-type="{{ $mapping->repair_type }}"
                                    data-priority="{{ $mapping->priority }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    data-id="{{ $mapping->id }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-diagram-3 fs-3 d-block mb-2"></i>
                        No mapping rules yet. Click <strong>Add Rule</strong> to create the first one.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $mappings->count() }} rules · {{ $mappings->where('is_active', true)->count() }} active
        </span>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.repair-category-mappings.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Mapping Rule</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">→ Repair Category <span class="text-danger">*</span></label>
                        <select name="repair_category_id" class="form-select" required>
                            <option value="">— Select category —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->code }} — {{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-muted small mb-2">Set at least one matching criterion below:</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">When Component Code is</label>
                        <select name="component_code_id" class="form-select">
                            <option value="">— Any —</option>
                            @foreach($componentCodes as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">When Repair Type is</label>
                        <select name="repair_type" class="form-select">
                            <option value="">— Any —</option>
                            @foreach($repairTypes as $rt)
                            <option value="{{ $rt }}">{{ ucfirst(str_replace('_', ' ', $rt)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                        <input type="number" name="priority" class="form-control" value="50" min="1" max="999" required>
                        <div class="form-text">Lower number = evaluated first. Use 10 for specific rules, 50 for general.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">→ Repair Category <span class="text-danger">*</span></label>
                        <select name="repair_category_id" id="editCategory" class="form-select" required>
                            <option value="">— Select category —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->code }} — {{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">When Component Code is</label>
                        <select name="component_code_id" id="editComponent" class="form-select">
                            <option value="">— Any —</option>
                            @foreach($componentCodes as $cc)
                            <option value="{{ $cc->id }}">{{ $cc->code }} — {{ $cc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">When Repair Type is</label>
                        <select name="repair_type" id="editRepairType" class="form-select">
                            <option value="">— Any —</option>
                            @foreach($repairTypes as $rt)
                            <option value="{{ $rt }}">{{ ucfirst(str_replace('_', ' ', $rt)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Priority <span class="text-danger">*</span></label>
                        <input type="number" name="priority" id="editPriority" class="form-control" min="1" max="999" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Rule</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete this mapping rule? This won't affect existing work orders.</p>
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
<script>
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editCategory').value    = btn.dataset.category;
        document.getElementById('editComponent').value  = btn.dataset.component ?? '';
        document.getElementById('editRepairType').value = btn.dataset.repairType ?? '';
        document.getElementById('editPriority').value   = btn.dataset.priority;
        document.getElementById('editForm').action      = '/masters/repair-category-mappings/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteForm').action = '/masters/repair-category-mappings/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
