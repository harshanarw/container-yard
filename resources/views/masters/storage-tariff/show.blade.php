@extends('layouts.app')

@section('title', 'Storage Tariff — ' . ($storageTariff->customer->name ?? 'Detail'))

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item">
        <a href="{{ route('masters.storage-tariff.index') }}" class="text-decoration-none">Storage Rate Tariff</a>
    </li>
    <li class="breadcrumb-item active">
        {{ $storageTariff->customer->code ?? '#' . $storageTariff->id }}
    </li>
@endsection

@push('styles')
<style>
    .rate-row td { vertical-align: middle; }
    .currency-badge { font-size: .7rem; letter-spacing: .04em; }
</style>
@endpush

@section('content')

{{-- ── Flash messages ── --}}
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

{{-- ── Page header ── --}}
<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4>
            <i class="bi bi-calendar2-range me-2 text-primary"></i>
            Storage Tariff
            <span class="badge {{ $storageTariff->is_active ? 'bg-success' : 'bg-secondary' }} ms-2"
                  style="font-size:.65rem;vertical-align:middle;">
                {{ $storageTariff->is_active ? 'Active' : 'Inactive' }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $storageTariff->customer->name ?? '—' }} &nbsp;·&nbsp;
            {{ $storageTariff->validity_label }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('masters.storage-tariff.index') }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <form method="POST" action="{{ route('masters.storage-tariff.toggle', $storageTariff) }}">
            @csrf @method('PATCH')
            <button type="submit"
                    class="btn btn-sm {{ $storageTariff->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                <i class="bi {{ $storageTariff->is_active ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>
                {{ $storageTariff->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
    </div>
</div>

<div class="row g-4">

    {{-- ════════════════════════════════════════════════════════════════════
         LEFT — Header edit form
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="col-lg-4">
        <div class="card content-card h-100">
            <div class="card-header py-2">
                <i class="bi bi-person-badge me-2 text-primary"></i>Tariff Header
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('masters.storage-tariff.update', $storageTariff) }}">
                    @csrf @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Customer <span class="text-danger">*</span>
                        </label>
                        <select name="customer_id" class="form-select select2" required>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    {{ $storageTariff->customer_id == $customer->id ? 'selected' : '' }}>
                                    [{{ $customer->code }}] {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Default Free Days <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number" name="default_free_days"
                                   class="form-control"
                                   value="{{ $storageTariff->default_free_days }}"
                                   min="0" max="365" required>
                            <span class="input-group-text small">days</span>
                        </div>
                        <div class="form-text">Storage is free for this many days after gate-in.</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">
                                Valid From <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="valid_from" class="form-control"
                                   value="{{ $storageTariff->valid_from->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Valid To</label>
                            <input type="date" name="valid_to" class="form-control"
                                   value="{{ $storageTariff->valid_to ? $storageTariff->valid_to->format('Y-m-d') : '' }}">
                            <div class="form-text">Blank = open-ended</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   id="editIsActive" value="1"
                                   {{ $storageTariff->is_active ? 'checked' : '' }}>
                            <label class="form-check-label small" for="editIsActive">
                                Active
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-save me-1"></i>Save Header
                    </button>
                </form>

                {{-- ── Audit info ── --}}
                <hr class="my-3">
                <div class="small text-muted">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-person-plus me-1"></i>Added by</span>
                        <span class="fw-semibold text-dark">{{ $storageTariff->createdBy->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-calendar me-1"></i>Added on</span>
                        <span>{{ $storageTariff->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-person-check me-1"></i>Updated by</span>
                        <span class="fw-semibold text-dark">{{ $storageTariff->updatedBy->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-clock me-1"></i>Updated on</span>
                        <span>{{ $storageTariff->updated_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         RIGHT — Rate lines
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="col-lg-8">
        <div class="card content-card">
            <div class="card-header d-flex align-items-center justify-content-between py-2">
                <span><i class="bi bi-currency-dollar me-2 text-primary"></i>Rate Lines</span>
                <span class="badge bg-primary-subtle text-primary">
                    {{ $storageTariff->details->count() }} equipment type(s)
                </span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0" id="rateTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Equipment Type</th>
                            <th style="width:80px;" class="text-center">ISO Code</th>
                            <th style="width:100px;" class="text-end">Daily Rate</th>
                            <th style="width:80px;" class="text-center">Currency</th>
                            <th style="width:100px;" class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($storageTariff->details as $detail)
                        <tr class="rate-row" data-detail-id="{{ $detail->id }}">
                            <td class="ps-3">
                                <span class="badge bg-primary fw-bold me-2"
                                      style="font-size:.8rem;letter-spacing:.5px;">
                                    {{ $detail->equipmentType->eqt_code ?? '—' }}
                                </span>
                                <span class="small text-muted">
                                    {{ $detail->equipmentType->description ?? '' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <code class="small">{{ $detail->equipmentType->iso_code ?? '—' }}</code>
                            </td>
                            <td class="text-end fw-semibold">
                                {{ number_format($detail->storage_rate, 2) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success currency-badge fw-bold">
                                    {{ $detail->currency }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary btn-edit-rate"
                                            data-id="{{ $detail->id }}"
                                            data-eqt="{{ $detail->equipmentType->eqt_code ?? '' }}"
                                            data-desc="{{ $detail->equipmentType->description ?? '' }}"
                                            data-rate="{{ $detail->storage_rate }}"
                                            data-currency="{{ $detail->currency }}"
                                            title="Edit rate">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-delete-rate"
                                            data-id="{{ $detail->id }}"
                                            data-label="{{ $detail->equipmentType->eqt_code ?? 'this line' }}"
                                            title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr id="emptyRow">
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                No rate lines yet. Add one below.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ── Add rate line inline form ── --}}
            <div class="card-footer bg-light border-top">
                <p class="small fw-semibold mb-2">
                    <i class="bi bi-plus-circle me-1 text-primary"></i>Add Rate Line
                    @if($availableTypes->isEmpty())
                        <span class="text-muted fw-normal ms-2">— all active equipment types already have a rate.</span>
                    @endif
                </p>
                @if($availableTypes->isNotEmpty())
                <form method="POST"
                      action="{{ route('masters.storage-tariff.details.store', $storageTariff) }}"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">
                            Equipment Type <span class="text-danger">*</span>
                        </label>
                        <select name="equipment_type_id" class="form-select form-select-sm select2" required>
                            <option value="">— Select —</option>
                            @foreach($availableTypes as $eqt)
                                <option value="{{ $eqt->id }}">
                                    {{ $eqt->eqt_code }}
                                    @if($eqt->description) — {{ $eqt->description }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">
                            Daily Rate <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="storage_rate" class="form-control form-control-sm"
                               step="0.01" min="0" max="99999.99" placeholder="0.00" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Currency</label>
                        <select name="currency" class="form-select form-select-sm">
                            <option value="USD" selected>USD</option>
                            <option value="EUR">EUR</option>
                            <option value="SGD">SGD</option>
                            <option value="MYR">MYR</option>
                            <option value="LKR">LKR</option>
                            <option value="AED">AED</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus-circle me-1"></i>Add Rate
                        </button>
                    </div>
                </form>
                @endif
            </div>
        </div>

        {{-- ── Summary tile ── --}}
        @if($storageTariff->details->count())
        <div class="card content-card mt-3">
            <div class="card-header py-2">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Rate Summary
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    @foreach($storageTariff->details as $detail)
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-2 text-center bg-light">
                            <div class="badge bg-primary mb-1"
                                 style="font-size:.75rem;">
                                {{ $detail->equipmentType->eqt_code ?? '—' }}
                            </div>
                            <div class="fw-bold small">
                                {{ $detail->currency }}
                                {{ number_format($detail->storage_rate, 2) }}
                            </div>
                            <div class="text-muted" style="font-size:.68rem;">per day</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

</div>{{-- /row --}}

{{-- ══════════════════════════════════════════════════════
     EDIT RATE MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editRateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="editRateForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-pencil me-1 text-primary"></i>
                        Edit Rate — <span id="editRateEqt"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="editRateDesc"></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Daily Rate <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="storage_rate" id="editRateValue"
                               class="form-control" step="0.01" min="0" max="99999.99" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-semibold">Currency</label>
                        <select name="currency" id="editRateCurrency" class="form-select">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="SGD">SGD</option>
                            <option value="MYR">MYR</option>
                            <option value="LKR">LKR</option>
                            <option value="AED">AED</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════
     DELETE RATE MODAL
══════════════════════════════════════════════════════ --}}
<div class="modal fade" id="deleteRateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>Remove Rate Line
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">
                    Remove the rate line for <strong id="deleteRateLabel"></strong>?
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <form id="deleteRateForm" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const baseDetailUrl = '{{ url("masters/storage-tariff/{$storageTariff->id}/details") }}';

document.addEventListener('DOMContentLoaded', () => {

    // ── Edit rate modal ──────────────────────────────────────────────────────
    document.querySelectorAll('.btn-edit-rate').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editRateEqt').textContent      = btn.dataset.eqt;
            document.getElementById('editRateDesc').textContent     = btn.dataset.desc || '';
            document.getElementById('editRateValue').value          = btn.dataset.rate;
            document.getElementById('editRateCurrency').value       = btn.dataset.currency;
            document.getElementById('editRateForm').action          = baseDetailUrl + '/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('editRateModal')).show();
        });
    });

    // ── Delete rate modal ────────────────────────────────────────────────────
    document.querySelectorAll('.btn-delete-rate').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('deleteRateLabel').textContent = btn.dataset.label;
            document.getElementById('deleteRateForm').action       = baseDetailUrl + '/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('deleteRateModal')).show();
        });
    });
});
</script>
@endpush
