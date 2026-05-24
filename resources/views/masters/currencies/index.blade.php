@extends('layouts.app')

@section('title', 'Currency Types')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Invoice</li>
    <li class="breadcrumb-item active">Currency Types</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-currency-exchange me-2 text-primary"></i>Currency Types</h4>
        <p class="text-muted mb-0 small">Define available currencies for invoicing. Drag rows to reorder. The default currency is highlighted.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Currency
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
        <table class="table table-hover align-middle mb-0" id="currencyTable">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:36px;"></th>
                    <th style="width:36px;">#</th>
                    <th style="width:90px;">Code</th>
                    <th style="width:60px;" class="text-center">Symbol</th>
                    <th>Currency Name</th>
                    <th style="width:180px;">Country / Region</th>
                    <th style="width:110px;" class="text-center">Default</th>
                    <th style="width:90px;" class="text-center">Status</th>
                    <th style="width:120px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody id="sortableBody">
            @forelse($currencies as $cur)
                <tr data-id="{{ $cur->id }}"
                    class="{{ $cur->is_default ? 'table-warning' : ($cur->is_active ? '' : 'table-secondary text-muted') }}">
                    <td class="ps-3 drag-handle" style="cursor:grab;" title="Drag to reorder">
                        <i class="bi bi-grip-vertical text-muted"></i>
                    </td>
                    <td class="small text-muted fw-semibold">{{ $cur->sort_order }}</td>
                    <td>
                        <span class="badge fw-bold {{ $cur->is_default ? 'bg-warning text-dark' : 'bg-primary' }}"
                              style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $cur->code }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="text-muted small font-monospace">{{ $cur->symbol ?? '—' }}</span>
                    </td>
                    <td>
                        <span class="fw-semibold {{ $cur->is_default ? 'text-warning-emphasis' : '' }}">
                            {{ $cur->name }}
                        </span>
                        @if($cur->is_default)
                            <span class="badge bg-warning text-dark ms-1" style="font-size:.7rem;">
                                <i class="bi bi-star-fill me-1"></i>Default
                            </span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $cur->country ?? '—' }}</td>
                    <td class="text-center">
                        @if($cur->is_default)
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-star-fill me-1"></i>System Default
                            </span>
                        @else
                            <form method="POST" action="{{ route('masters.currencies.set-default', $cur) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                        title="Set as default currency"
                                        onclick="return confirm('Set {{ $cur->code }} as the system default currency?')">
                                    <i class="bi bi-star me-1"></i>Set Default
                                </button>
                            </form>
                        @endif
                    </td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('masters.currencies.toggle', $cur) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="btn btn-sm {{ $cur->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                    title="{{ $cur->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}"
                                    {{ $cur->is_default ? 'disabled title=Cannot deactivate default currency' : '' }}>
                                <i class="bi {{ $cur->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary btn-edit"
                                    data-id="{{ $cur->id }}"
                                    data-code="{{ $cur->code }}"
                                    data-name="{{ $cur->name }}"
                                    data-country="{{ $cur->country ?? '' }}"
                                    data-symbol="{{ $cur->symbol ?? '' }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-delete"
                                    data-id="{{ $cur->id }}"
                                    data-label="{{ $cur->code }}"
                                    data-default="{{ $cur->is_default ? '1' : '0' }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-currency-exchange fs-3 d-block mb-2"></i>
                        No currencies yet. Click <strong>Add Currency</strong> to get started.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="text-muted small">
            {{ $currencies->count() }} total · {{ $currencies->where('is_active', true)->count() }} active
        </span>
        @php $default = $currencies->firstWhere('is_default', true); @endphp
        @if($default)
            <span class="small text-muted">
                <i class="bi bi-star-fill text-warning me-1"></i>
                System default: <strong>{{ $default->code }} — {{ $default->name }}</strong>
            </span>
        @endif
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.currencies.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>Add Currency</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase"
                                   maxlength="3" minlength="3" required placeholder="e.g. USD">
                            <div class="form-text">3-letter ISO 4217 code</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Symbol</label>
                            <input type="text" name="symbol" class="form-control"
                                   maxlength="10" placeholder="e.g. $">
                        </div>
                        <div class="col-md-4">
                            {{-- spacer --}}
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Currency Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   maxlength="100" required placeholder="e.g. US Dollar">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Country / Region</label>
                            <input type="text" name="country" class="form-control"
                                   maxlength="100" placeholder="e.g. United States">
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
                    <h6 class="modal-title"><i class="bi bi-pencil me-1 text-primary"></i>Edit Currency</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="editCode" class="form-control text-uppercase"
                                   maxlength="3" minlength="3" required>
                            <div class="form-text">3-letter ISO 4217 code</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Symbol</label>
                            <input type="text" name="symbol" id="editSymbol" class="form-control" maxlength="10">
                        </div>
                        <div class="col-md-4">
                            {{-- spacer --}}
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Currency Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="editName" class="form-control"
                                   maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Country / Region</label>
                            <input type="text" name="country" id="editCountry" class="form-control" maxlength="100">
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
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Currency</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete currency <strong id="deleteLabel"></strong>? This cannot be undone.</p>
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
            fetch('{{ route("masters.currencies.reorder") }}', {
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
        document.getElementById('editCode').value    = btn.dataset.code;
        document.getElementById('editName').value    = btn.dataset.name;
        document.getElementById('editCountry').value = btn.dataset.country;
        document.getElementById('editSymbol').value  = btn.dataset.symbol;
        document.getElementById('editForm').action   =
            '{{ url("masters/currencies") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ─────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.dataset.default === '1') {
            alert('Cannot delete the default currency. Set another currency as default first.');
            return;
        }
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/currencies") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
