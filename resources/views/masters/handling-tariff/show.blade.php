@extends('layouts.app')

@section('title', 'Handling Tariff — ' . ($handlingTariff->shippingLine->name ?? 'Detail'))

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item">
        <a href="{{ route('masters.handling-tariff.index') }}" class="text-decoration-none">Handling Charges Tariff</a>
    </li>
    <li class="breadcrumb-item active">
        {{ $handlingTariff->shippingLine->code ?? '#' . $handlingTariff->id }}
    </li>
@endsection

@push('styles')
<style>
    .rate-row td { vertical-align: middle; }
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
            <i class="bi bi-truck me-2 text-primary"></i>
            Handling Charges Tariff
            <span class="badge {{ $handlingTariff->is_active ? 'bg-success' : 'bg-secondary' }} ms-2"
                  style="font-size:.65rem;vertical-align:middle;">
                {{ $handlingTariff->is_active ? 'Active' : 'Inactive' }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $handlingTariff->shippingLine->name ?? '—' }} &nbsp;·&nbsp;
            {{ $handlingTariff->validity_label }}
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('masters.handling-tariff.index') }}"
           class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <form method="POST" action="{{ route('masters.handling-tariff.toggle', $handlingTariff) }}">
            @csrf @method('PATCH')
            <button type="submit"
                    class="btn btn-sm {{ $handlingTariff->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                <i class="bi {{ $handlingTariff->is_active ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>
                {{ $handlingTariff->is_active ? 'Deactivate' : 'Activate' }}
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
                <i class="bi bi-building me-2 text-primary"></i>Tariff Header
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('masters.handling-tariff.update', $handlingTariff) }}">
                    @csrf @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Customer <span class="text-danger">*</span>
                        </label>
                        <select name="shipping_line_id" class="form-select select2" required>
                            @foreach($customers as $line)
                                <option value="{{ $line->id }}"
                                    {{ $handlingTariff->shipping_line_id == $line->id ? 'selected' : '' }}>
                                    [{{ $line->code }}] {{ $line->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">
                                Valid From <span class="text-danger">*</span>
                            </label>
                            <input type="date" name="valid_from" class="form-control"
                                   value="{{ $handlingTariff->valid_from->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Valid To</label>
                            <input type="date" name="valid_to" class="form-control"
                                   value="{{ $handlingTariff->valid_to ? $handlingTariff->valid_to->format('Y-m-d') : '' }}">
                            <div class="form-text">Blank = open-ended</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"
                                  maxlength="500" placeholder="Optional remarks…">{{ $handlingTariff->notes }}</textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active"
                                   id="editIsActive" value="1"
                                   {{ $handlingTariff->is_active ? 'checked' : '' }}>
                            <label class="form-check-label small" for="editIsActive">Active</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-save me-1"></i>Save Header
                    </button>
                </form>

                <hr class="my-3">
                <div class="small text-muted">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-person-plus me-1"></i>Added by</span>
                        <span class="fw-semibold text-dark">{{ $handlingTariff->createdBy->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-calendar me-1"></i>Added on</span>
                        <span>{{ $handlingTariff->created_at->format('d M Y, H:i') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-person-check me-1"></i>Updated by</span>
                        <span class="fw-semibold text-dark">{{ $handlingTariff->updatedBy->name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span><i class="bi bi-clock me-1"></i>Updated on</span>
                        <span>{{ $handlingTariff->updated_at->format('d M Y, H:i') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════
         RIGHT — Rate lines
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="col-lg-8">

        {{-- ── Rate explanation banner ── --}}
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Lift Off</strong> is charged at <strong>Gate In</strong> (taking the container off the vehicle).
            &nbsp;|&nbsp;
            <strong>Lift On</strong> is charged at <strong>Gate Out</strong> (placing the container onto the vehicle).
        </div>

        <div class="card content-card">
            <div class="card-header d-flex align-items-center justify-content-between py-2">
                <span><i class="bi bi-currency-dollar me-2 text-primary"></i>Rate Lines</span>
                <span class="badge bg-primary-subtle text-primary">
                    {{ $handlingTariff->rates->count() }} rate line(s)
                </span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:120px;">Container Size</th>
                            <th style="width:90px;" class="text-center">Status</th>
                            <th class="text-end" style="width:120px;">
                                <span class="text-success">
                                    <i class="bi bi-arrow-down-circle me-1"></i>Lift Off
                                </span>
                                <div class="text-muted fw-normal" style="font-size:.68rem;">Gate In</div>
                            </th>
                            <th class="text-end" style="width:120px;">
                                <span class="text-primary">
                                    <i class="bi bi-arrow-up-circle me-1"></i>Lift On
                                </span>
                                <div class="text-muted fw-normal" style="font-size:.68rem;">Gate Out</div>
                            </th>
                            <th class="text-center" style="width:80px;">Currency</th>
                            <th style="width:150px;">Charge Code / Tax</th>
                            <th class="text-end pe-3" style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($handlingTariff->rates as $rate)
                        <tr class="rate-row">
                            <td class="ps-3">
                                <span class="badge bg-dark fw-bold fs-6 me-1">{{ $rate->container_size }}'</span>
                            </td>
                            <td class="text-center">
                                @if($rate->cargo_status === 'laden')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-semibold" style="font-size:.72rem;">Laden</span>
                                @else
                                    <span class="badge bg-info-subtle text-info border border-info-subtle fw-semibold" style="font-size:.72rem;">Empty</span>
                                @endif
                            </td>
                            <td class="text-end fw-semibold text-success">
                                {{ number_format($rate->lift_off_rate, 2) }}
                            </td>
                            <td class="text-end fw-semibold text-primary">
                                {{ number_format($rate->lift_on_rate, 2) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success fw-bold" style="font-size:.7rem;">
                                    {{ $rate->currency }}
                                </span>
                            </td>
                            <td>
                                @if($rate->chargeCode)
                                    <span class="badge bg-primary fw-bold" style="font-size:.75rem;">
                                        {{ $rate->chargeCode->code }}
                                    </span>
                                    @if($rate->chargeCode->taxCode)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle ms-1" style="font-size:.7rem;">
                                            {{ $rate->chargeCode->taxCode->code }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex flex-wrap justify-content-end gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm btn-edit-rate"
                                            data-id="{{ $rate->id }}"
                                            data-size="{{ $rate->container_size }}"
                                            data-cargo_status="{{ $rate->cargo_status }}"
                                            data-liftoff="{{ $rate->lift_off_rate }}"
                                            data-lifton="{{ $rate->lift_on_rate }}"
                                            data-currency="{{ $rate->currency }}"
                                            data-charge_code_id="{{ $rate->charge_code_id ?? '' }}"
                                            title="Edit rate">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm btn-delete-rate"
                                            data-id="{{ $rate->id }}"
                                            data-size="{{ $rate->container_size }}"
                                            data-cargo_status="{{ $rate->cargo_status }}"
                                            title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                                No rate lines yet. Add one below.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ── Add rate line ── --}}
            <div class="card-footer bg-light border-top">
                <p class="small fw-semibold mb-2">
                    <i class="bi bi-plus-circle me-1 text-primary"></i>Add Rate Line
                </p>
                <form method="POST"
                      action="{{ route('masters.handling-tariff.rates.store', $handlingTariff) }}"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">
                            Size <span class="text-danger">*</span>
                        </label>
                        <select name="container_size" class="form-select form-select-sm" required>
                            <option value="">—</option>
                            @foreach($allSizes as $size)
                                <option value="{{ $size }}">{{ $size }}'</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">
                            Laden / Empty <span class="text-danger">*</span>
                        </label>
                        <select name="cargo_status" class="form-select form-select-sm" required>
                            <option value="empty" selected>Empty</option>
                            <option value="laden">Laden</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">
                            <span class="text-success"><i class="bi bi-arrow-down-circle me-1"></i>Lift Off</span>
                            <span class="text-muted fw-normal">(Gate In)</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="lift_off_rate" class="form-control form-control-sm"
                               step="0.01" min="0" max="99999.99" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">
                            <span class="text-primary"><i class="bi bi-arrow-up-circle me-1"></i>Lift On</span>
                            <span class="text-muted fw-normal">(Gate Out)</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="number" name="lift_on_rate" class="form-control form-control-sm"
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
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">
                            <i class="bi bi-tag me-1 text-primary"></i>Charge Code
                        </label>
                        <select name="charge_code_id" class="form-select form-select-sm select2">
                            <option value="">— None —</option>
                            @foreach($chargeCodes as $cc)
                                <option value="{{ $cc->id }}" {{ $cc->code === 'LOLO' ? 'selected' : '' }}>
                                    {{ $cc->code }} — {{ $cc->description }}
                                    @if($cc->taxCode) ({{ $cc->taxCode->code }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100 mt-3">
                            <i class="bi bi-plus-circle me-1"></i>Add
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Summary tiles ── --}}
        @if($handlingTariff->rates->count())
        <div class="card content-card mt-3">
            <div class="card-header py-2">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Rate Summary
            </div>
            <div class="card-body py-2">
                <div class="row g-2">
                    @foreach($handlingTariff->rates as $rate)
                    <div class="col-12 col-md-4">
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge bg-dark fs-6">{{ $rate->container_size }}'</span>
                                <div class="d-flex gap-1">
                                    @if($rate->cargo_status === 'laden')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.65rem;">Laden</span>
                                    @else
                                        <span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.65rem;">Empty</span>
                                    @endif
                                    <span class="badge bg-success-subtle text-success fw-bold" style="font-size:.7rem;">
                                        {{ $rate->currency }}
                                    </span>
                                </div>
                            </div>
                            <div class="row g-0 text-center">
                                <div class="col-6 border-end">
                                    <div class="text-muted" style="font-size:.68rem;">
                                        <i class="bi bi-arrow-down-circle text-success"></i> Lift Off
                                    </div>
                                    <div class="fw-bold small text-success">
                                        {{ number_format($rate->lift_off_rate, 2) }}
                                    </div>
                                    <div class="text-muted" style="font-size:.63rem;">Gate In</div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted" style="font-size:.68rem;">
                                        <i class="bi bi-arrow-up-circle text-primary"></i> Lift On
                                    </div>
                                    <div class="fw-bold small text-primary">
                                        {{ number_format($rate->lift_on_rate, 2) }}
                                    </div>
                                    <div class="text-muted" style="font-size:.63rem;">Gate Out</div>
                                </div>
                            </div>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editRateForm">
                @csrf @method('PATCH')
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-pencil me-1 text-primary"></i>
                        Edit Rate — <span id="editRateSize"></span>' Container
                        <span id="editRateStatusBadge" class="ms-1"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small py-2 mb-3">
                        <i class="bi bi-info-circle me-1 text-info"></i>
                        <strong>Lift Off</strong> applies at Gate In &nbsp;|&nbsp;
                        <strong>Lift On</strong> applies at Gate Out
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">
                                Laden / Empty <span class="text-danger">*</span>
                            </label>
                            <select name="cargo_status" id="editRateCargoStatus" class="form-select">
                                <option value="empty">Empty</option>
                                <option value="laden">Laden</option>
                            </select>
                        </div>
                        <div class="col-6">
                            {{-- spacer to keep layout balanced --}}
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-arrow-down-circle text-success me-1"></i>
                                Lift Off Rate <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="lift_off_rate" id="editLiftOffRate"
                                   class="form-control" step="0.01" min="0" max="99999.99" required>
                            <div class="form-text">Charged at Gate In</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-arrow-up-circle text-primary me-1"></i>
                                Lift On Rate <span class="text-danger">*</span>
                            </label>
                            <input type="number" name="lift_on_rate" id="editLiftOnRate"
                                   class="form-control" step="0.01" min="0" max="99999.99" required>
                            <div class="form-text">Charged at Gate Out</div>
                        </div>
                        <div class="col-6">
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
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-tag me-1 text-primary"></i>Charge Code
                            </label>
                            <select name="charge_code_id" id="editRateChargeCode" class="form-select select2-modal">
                                <option value="">— None —</option>
                                @foreach($chargeCodes as $cc)
                                    <option value="{{ $cc->id }}">
                                        {{ $cc->code }} — {{ $cc->description }}
                                        @if($cc->taxCode) ({{ $cc->taxCode->code }}) @endif
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Tax rates derive from the linked charge code.</div>
                        </div>
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
                    Remove the rate line for <strong id="deleteRateSize"></strong>' containers?
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
const baseRateUrl = '{{ url("masters/handling-tariff/{$handlingTariff->id}/rates") }}';

document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.btn-edit-rate').forEach(btn => {
        btn.addEventListener('click', () => {
            const status = btn.dataset.cargo_status || 'empty';
            document.getElementById('editRateSize').textContent      = btn.dataset.size;
            document.getElementById('editRateStatusBadge').innerHTML =
                status === 'laden'
                    ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.7rem;">Laden</span>'
                    : '<span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.7rem;">Empty</span>';
            document.getElementById('editRateCargoStatus').value     = status;
            document.getElementById('editLiftOffRate').value         = btn.dataset.liftoff;
            document.getElementById('editLiftOnRate').value          = btn.dataset.lifton;
            document.getElementById('editRateCurrency').value        = btn.dataset.currency;
            document.getElementById('editRateChargeCode').value      = btn.dataset.charge_code_id || '';
            document.getElementById('editRateForm').action           = baseRateUrl + '/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('editRateModal')).show();
        });
    });

    // Initialise select2 inside the edit modal after it fully opens
    $('#editRateModal').on('shown.bs.modal', function () {
        $(this).find('.select2-modal').select2({ dropdownParent: $(this), width: '100%' });
    });

    document.querySelectorAll('.btn-delete-rate').forEach(btn => {
        btn.addEventListener('click', () => {
            const status = btn.dataset.cargo_status || 'empty';
            const statusLabel = status === 'laden' ? 'Laden' : 'Empty';
            document.getElementById('deleteRateSize').textContent = btn.dataset.size + "' " + statusLabel;
            document.getElementById('deleteRateForm').action      = baseRateUrl + '/' + btn.dataset.id;
            new bootstrap.Modal(document.getElementById('deleteRateModal')).show();
        });
    });
});
</script>
@endpush
