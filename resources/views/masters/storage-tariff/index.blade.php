@extends('layouts.app')

@section('title', 'Storage Rate Tariff')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item active">Storage Rate Tariff</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-calendar2-range me-2 text-primary"></i>Storage Rate Tariff</h4>
        <p class="text-muted mb-0 small">
            Define per-customer, per-equipment-type daily storage rates with validity periods.
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
            <div class="fs-3 fw-bold text-primary">{{ $headers->count() }}</div>
            <div class="text-muted small">Total Tariffs</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $headers->where('is_active', true)->count() }}</div>
            <div class="text-muted small">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-secondary">{{ $headers->where('is_active', false)->count() }}</div>
            <div class="text-muted small">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-info">{{ $headers->pluck('customer_id')->unique()->count() }}</div>
            <div class="text-muted small">Customers Covered</div>
        </div>
    </div>
</div>

{{-- ── Tariff table ── --}}
<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-table me-2 text-primary"></i>All Tariff Headers</span>
        <span class="badge bg-primary-subtle text-primary">{{ $headers->count() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tariffTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px;">#</th>
                        <th>Customer</th>
                        <th style="width:110px;" class="text-center">Free Days</th>
                        <th style="width:200px;">Validity Period</th>
                        <th style="width:90px;" class="text-center">Rate Lines</th>
                        <th style="width:90px;" class="text-center">Status</th>
                        <th style="width:130px;">Added By</th>
                        <th style="width:130px;">Updated By</th>
                        <th style="width:110px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($headers as $header)
                    @php
                        $now     = now();
                        $expired = $header->valid_to && $header->valid_to->lt($now);
                        $pending = $header->valid_from->gt($now);
                        $rowCls  = ! $header->is_active ? 'table-secondary text-muted' : ($expired ? 'table-warning' : '');
                    @endphp
                    <tr class="{{ $rowCls }}">
                        <td class="ps-3 text-muted small fw-semibold">{{ $header->id }}</td>
                        <td>
                            <div class="fw-semibold small">{{ $header->customer->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.72rem;">
                                {{ $header->customer->code ?? '' }}
                                @if($header->customer->type ?? false)
                                    &nbsp;·&nbsp;
                                    <span class="text-capitalize">{{ str_replace('_', ' ', $header->customer->type) }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-info-subtle text-info fw-semibold">
                                {{ $header->default_free_days }} days
                            </span>
                        </td>
                        <td>
                            <div class="small">
                                <i class="bi bi-calendar-check text-success me-1"></i>
                                {{ $header->valid_from->format('d M Y') }}
                            </div>
                            <div class="small">
                                @if($header->valid_to)
                                    <i class="bi bi-calendar-x {{ $expired ? 'text-danger' : 'text-muted' }} me-1"></i>
                                    {{ $header->valid_to->format('d M Y') }}
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
                            <a href="{{ route('masters.storage-tariff.show', $header) }}"
                               class="badge bg-primary text-white text-decoration-none"
                               title="View rate lines">
                                {{ $header->details_count }}
                                <i class="bi bi-arrow-right-short"></i>
                            </a>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('masters.storage-tariff.toggle', $header) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-sm {{ $header->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                        title="{{ $header->is_active ? 'Active – click to deactivate' : 'Inactive – click to activate' }}">
                                    <i class="bi {{ $header->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="small">{{ $header->createdBy->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.7rem;">{{ $header->created_at->format('d M Y') }}</div>
                        </td>
                        <td>
                            <div class="small">{{ $header->updatedBy->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:.7rem;">{{ $header->updated_at->format('d M Y') }}</div>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('masters.storage-tariff.show', $header) }}"
                                   class="btn btn-outline-primary" title="View / Edit Rates">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-delete"
                                        data-id="{{ $header->id }}"
                                        data-label="{{ $header->customer->name ?? 'this tariff' }}"
                                        data-validity="{{ $header->validity_label }}"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-calendar2-range fs-3 d-block mb-2"></i>
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
            {{ $headers->count() }} tariff(s)
            · {{ $headers->where('is_active', true)->count() }} active
            · {{ $headers->where('is_active', false)->count() }} inactive
        </span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     ADD MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.storage-tariff.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-plus-circle me-1 text-primary"></i>New Storage Tariff
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Create the tariff header here. After saving, you will be taken to the
                        detail page where you can add per-equipment-type rate lines.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Customer <span class="text-danger">*</span>
                            </label>
                            <select name="customer_id" class="form-select select2" required>
                                <option value="">— Select Customer —</option>
                                @foreach($customers as $customer)
                                    <option value="{{ $customer->id }}">
                                        [{{ $customer->code }}] {{ $customer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">
                                Free Days <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number" name="default_free_days"
                                       class="form-control" value="7" min="0" max="365" required>
                                <span class="input-group-text small">days</span>
                            </div>
                            <div class="form-text">Days before billing starts</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">
                                Valid From <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="valid_from" class="form-control"
                                   value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Valid To</label>
                            <input type="date" name="valid_to" class="form-control">
                            <div class="form-text">Leave blank for open-ended</div>
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
                    Delete tariff for <strong id="deleteLabel"></strong>?
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
    // DataTable
    if ($.fn.DataTable) {
        $('#tariffTable').DataTable({
            pageLength: 15,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [4, 5, 8] },
            ],
        });
    }

    // Delete modal
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteLabel').textContent   = btn.dataset.label;
            document.getElementById('deleteValidity').textContent = btn.dataset.validity;
            document.getElementById('deleteForm').action =
                '{{ url("masters/storage-tariff") }}/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        });
    });
});
</script>
@endpush
