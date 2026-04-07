@extends('layouts.app')

@section('title', 'Handling Charges Tariff')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item active">Handling Charges Tariff</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-truck me-2 text-primary"></i>Handling Charges Tariff</h4>
        <p class="text-muted mb-0 small">
            Define Lift On / Lift Off handling rates per shipping line and container size.
        </p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>New Tariff
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

{{-- ── Stats strip ── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-primary">{{ $tariffs->count() }}</div>
            <div class="text-muted small">Total Tariffs</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $tariffs->where('is_active', true)->count() }}</div>
            <div class="text-muted small">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-secondary">{{ $tariffs->where('is_active', false)->count() }}</div>
            <div class="text-muted small">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-info">{{ $tariffs->pluck('shipping_line_id')->unique()->count() }}</div>
            <div class="text-muted small">Shipping Lines</div>
        </div>
    </div>
</div>

{{-- ── Tariff table ── --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-table me-2 text-primary"></i>All Handling Tariffs</span>
        <span class="badge bg-primary-subtle text-primary">{{ $tariffs->count() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tariffTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px;">#</th>
                        <th>Shipping Line</th>
                        <th style="width:200px;">Validity Period</th>
                        <th style="width:90px;" class="text-center">Size Rates</th>
                        <th style="width:90px;" class="text-center">Status</th>
                        <th style="width:130px;">Added By</th>
                        <th style="width:130px;">Updated By</th>
                        <th style="width:110px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tariffs as $tariff)
                    @php
                        $now     = now();
                        $expired = $tariff->valid_to && $tariff->valid_to->lt($now);
                        $rowCls  = ! $tariff->is_active ? 'table-secondary text-muted' : ($expired ? 'table-warning' : '');
                    @endphp
                    <tr class="{{ $rowCls }}">
                        <td class="ps-3 text-muted small fw-semibold">{{ $tariff->id }}</td>
                        <td>
                            <div class="fw-semibold small">{{ $tariff->shippingLine->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.72rem;">
                                {{ $tariff->shippingLine->code ?? '' }}
                            </div>
                        </td>
                        <td>
                            <div class="small">
                                <i class="bi bi-calendar-check text-success me-1"></i>
                                {{ $tariff->valid_from->format('d M Y') }}
                            </div>
                            <div class="small">
                                @if($tariff->valid_to)
                                    <i class="bi bi-calendar-x {{ $expired ? 'text-danger' : 'text-muted' }} me-1"></i>
                                    {{ $tariff->valid_to->format('d M Y') }}
                                    @if($expired)
                                        <span class="badge bg-danger-subtle text-danger ms-1" style="font-size:.65rem;">Expired</span>
                                    @endif
                                @else
                                    <i class="bi bi-infinity text-muted me-1"></i>
                                    <span class="text-muted">Open-ended</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('masters.handling-tariff.show', $tariff) }}"
                               class="badge bg-primary text-white text-decoration-none"
                               title="View rate lines">
                                {{ $tariff->rates_count }}
                                <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('masters.handling-tariff.toggle', $tariff) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-sm {{ $tariff->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                        title="{{ $tariff->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                    <i class="bi {{ $tariff->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="small">{{ $tariff->createdBy->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.7rem;">{{ $tariff->created_at->format('d M Y') }}</div>
                        </td>
                        <td>
                            <div class="small">{{ $tariff->updatedBy->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.7rem;">{{ $tariff->updated_at->format('d M Y') }}</div>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('masters.handling-tariff.show', $tariff) }}"
                                   class="btn btn-outline-primary" title="View / Edit Rates">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-delete"
                                        data-id="{{ $tariff->id }}"
                                        data-label="{{ $tariff->shippingLine->name ?? 'this tariff' }}"
                                        data-validity="{{ $tariff->validity_label }}"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-truck fs-3 d-block mb-2"></i>
                            No tariffs yet. Click <strong>New Tariff</strong> to get started.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white py-2">
        <span class="text-muted small">
            {{ $tariffs->count() }} tariff(s)
            · {{ $tariffs->where('is_active', true)->count() }} active
            · {{ $tariffs->where('is_active', false)->count() }} inactive
        </span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     ADD MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.handling-tariff.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-plus-circle me-1 text-primary"></i>New Handling Charges Tariff
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Select the shipping line and validity period. After saving you will be taken
                        to the detail page to add Lift On / Lift Off rates per container size.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Shipping Line <span class="text-danger">*</span>
                            </label>
                            <select name="shipping_line_id" class="form-select select2" required>
                                <option value="">— Select Shipping Line —</option>
                                @foreach($shippingLines as $line)
                                    <option value="{{ $line->id }}">
                                        [{{ $line->code }}] {{ $line->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($shippingLines->isEmpty())
                                <div class="form-text text-warning">
                                    No active shipping-line customers found. Add one under Customers first.
                                </div>
                            @endif
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                Valid From <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="valid_from" class="form-control"
                                   value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Valid To</label>
                            <input type="date" name="valid_to" class="form-control">
                            <div class="form-text">Leave blank for open-ended</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" class="form-control form-control-sm"
                                   placeholder="Optional remarks…" maxlength="500">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       id="addIsActive" value="1" checked>
                                <label class="form-check-label small" for="addIsActive">
                                    Active (tariff is immediately in effect)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right-circle me-1"></i>Create &amp; Add Rates
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     DELETE MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>Delete Tariff
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-1">
                    Delete handling tariff for <strong id="deleteLabel"></strong>?
                </p>
                <p class="small text-muted mb-0" id="deleteValidity"></p>
                <p class="small text-danger mt-2 mb-0">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    All rate lines for this tariff will also be deleted.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
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
document.addEventListener('DOMContentLoaded', () => {
    if ($.fn.DataTable) {
        $('#tariffTable').DataTable({
            pageLength: 15,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [3, 4, 7] },
            ],
        });
    }

    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteLabel').textContent    = btn.dataset.label;
            document.getElementById('deleteValidity').textContent = btn.dataset.validity;
            document.getElementById('deleteForm').action =
                '{{ url("masters/handling-tariff") }}/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
});
</script>
@endpush
