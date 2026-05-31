@extends('layouts.app')

@section('title', 'Daily Exchange Rates')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Invoice</li>
    <li class="breadcrumb-item active">Daily Exchange Rates</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-arrow-left-right me-2 text-primary"></i>Daily Exchange Rates</h4>
        <p class="text-muted mb-0 small">
            Manage daily currency exchange rates. Default pair:
            <strong>USD → {{ $defaultCurrency }}</strong>.
        </p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>Add Rate
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

{{-- ── Filter Bar ── --}}
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('masters.exchange-rates.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm mb-1 fw-semibold">From Currency</label>
                    <select name="from_currency" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($currencies as $cur)
                            <option value="{{ $cur->code }}"
                                    {{ request('from_currency') === $cur->code ? 'selected' : '' }}>
                                {{ $cur->code }} — {{ $cur->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label form-label-sm mb-1 fw-semibold">To Currency</label>
                    <select name="to_currency" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($currencies as $cur)
                            <option value="{{ $cur->code }}"
                                    {{ request('to_currency') === $cur->code ? 'selected' : '' }}>
                                {{ $cur->code }} — {{ $cur->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('masters.exchange-rates.index') }}"
                       class="btn btn-sm btn-outline-secondary ms-1">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── Rates Table ── --}}
<div class="card content-card">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:130px;">Date</th>
                    <th style="width:100px;" class="text-center">From</th>
                    <th style="width:36px;" class="text-center text-muted">→</th>
                    <th style="width:100px;" class="text-center">To</th>
                    <th style="width:160px;" class="text-end">Rate</th>
                    <th>Notes</th>
                    <th style="width:130px;" class="text-muted small">Added By</th>
                    <th style="width:120px;" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rates as $r)
                @php $isToday = $r->rate_date->isToday(); @endphp
                <tr class="{{ $isToday ? 'table-primary' : '' }}">
                    <td class="ps-3">
                        <span class="fw-semibold small">{{ $r->rate_date->format('d M Y') }}</span>
                        @if($isToday)
                            <span class="badge bg-primary ms-1" style="font-size:.65rem;">Today</span>
                        @elseif($r->rate_date->isYesterday())
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Yesterday</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge bg-dark fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $r->from_currency_code }}
                        </span>
                    </td>
                    <td class="text-center text-muted">
                        <i class="bi bi-arrow-right"></i>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success fw-bold" style="font-size:.8rem;letter-spacing:.5px;">
                            {{ $r->to_currency_code }}
                        </span>
                    </td>
                    <td class="text-end">
                        <span class="font-monospace fw-semibold">
                            {{ number_format((float) $r->rate, 4) }}
                        </span>
                        <div class="text-muted" style="font-size:.72rem;">
                            1 {{ $r->from_currency_code }} = {{ number_format((float) $r->rate, 4) }} {{ $r->to_currency_code }}
                        </div>
                    </td>
                    <td class="small text-muted">{{ $r->notes ?? '—' }}</td>
                    <td class="small text-muted">{{ $r->createdBy?->name ?? '—' }}</td>
                    <td class="text-end pe-3">
                        <div class="d-flex flex-wrap justify-content-end gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit"
                                    data-id="{{ $r->id }}"
                                    data-date="{{ $r->rate_date->format('Y-m-d') }}"
                                    data-from="{{ $r->from_currency_code }}"
                                    data-to="{{ $r->to_currency_code }}"
                                    data-rate="{{ $r->rate }}"
                                    data-notes="{{ $r->notes ?? '' }}"
                                    title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                    data-id="{{ $r->id }}"
                                    data-label="1 {{ $r->from_currency_code }} = {{ $r->rate }} {{ $r->to_currency_code }} ({{ $r->rate_date->format('d M Y') }})"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-arrow-left-right fs-3 d-block mb-2 opacity-25"></i>
                        No exchange rates found.
                        @if(request()->hasAny(['date_from','date_to','from_currency','to_currency']))
                            <a href="{{ route('masters.exchange-rates.index') }}" class="d-block mt-1 small">Clear filters</a>
                        @else
                            Click <strong>Add Rate</strong> to get started.
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-white py-2 d-flex justify-content-between align-items-center">
        <span class="text-muted small">
            @if($rates->total())
                Showing {{ $rates->firstItem() }}–{{ $rates->lastItem() }} of {{ $rates->total() }} rate(s)
            @else
                No records
            @endif
        </span>
        {{ $rates->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.exchange-rates.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title">
                        <i class="bi bi-plus-circle me-1 text-primary"></i>Add Exchange Rate
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="rate_date" class="form-control"
                                   value="{{ old('rate_date', date('Y-m-d')) }}" required>
                            <div class="form-text">The date this rate applies to</div>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">From Currency <span class="text-danger">*</span></label>
                            <select name="from_currency_code" id="addFromCurrency" class="form-select select2-modal s2-code" required>
                                @foreach($currencies as $cur)
                                    <option value="{{ $cur->code }}"
                                            data-code="{{ $cur->code }}" data-name="{{ $cur->name }}"
                                            {{ old('from_currency_code', 'USD') === $cur->code ? 'selected' : '' }}>
                                        {{ $cur->code }} — {{ $cur->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">To Currency <span class="text-danger">*</span></label>
                            <select name="to_currency_code" id="addToCurrency" class="form-select select2-modal s2-code" required>
                                @foreach($currencies as $cur)
                                    <option value="{{ $cur->code }}"
                                            data-code="{{ $cur->code }}" data-name="{{ $cur->name }}"
                                            {{ old('to_currency_code', $defaultCurrency) === $cur->code ? 'selected' : '' }}>
                                        {{ $cur->code }} — {{ $cur->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Exchange Rate <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text small" id="addRatePrefix">1 USD =</span>
                                <input type="number" name="rate" class="form-control"
                                       min="0" step="any"
                                       value="{{ old('rate') }}"
                                       placeholder="e.g. 300" required>
                                <span class="input-group-text small" id="addRateSuffix">{{ $defaultCurrency }}</span>
                            </div>
                            <div class="form-text">How many units of the <em>To</em> currency equal 1 unit of the <em>From</em> currency</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" class="form-control"
                                   maxlength="255"
                                   value="{{ old('notes') }}"
                                   placeholder="e.g. Central Bank mid-rate">
                        </div>

                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Save Rate
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
                    <h6 class="modal-title">
                        <i class="bi bi-pencil me-1 text-primary"></i>Edit Exchange Rate
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                            <input type="date" name="rate_date" id="editDate" class="form-control" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">From Currency <span class="text-danger">*</span></label>
                            <select name="from_currency_code" id="editFromCurrency" class="form-select select2-modal s2-code" required>
                                @foreach($currencies as $cur)
                                    <option value="{{ $cur->code }}" data-code="{{ $cur->code }}" data-name="{{ $cur->name }}">
                                        {{ $cur->code }} — {{ $cur->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-semibold">To Currency <span class="text-danger">*</span></label>
                            <select name="to_currency_code" id="editToCurrency" class="form-select select2-modal s2-code" required>
                                @foreach($currencies as $cur)
                                    <option value="{{ $cur->code }}" data-code="{{ $cur->code }}" data-name="{{ $cur->name }}">
                                        {{ $cur->code }} — {{ $cur->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Exchange Rate <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text small" id="editRatePrefix">1 — =</span>
                                <input type="number" name="rate" id="editRate" class="form-control"
                                       min="0" step="any" required>
                                <span class="input-group-text small" id="editRateSuffix">—</span>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <input type="text" name="notes" id="editNotes" class="form-control" maxlength="255">
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
                <h6 class="modal-title text-danger">
                    <i class="bi bi-exclamation-triangle me-1"></i>Delete Rate
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete rate <strong id="deleteLabel"></strong>? This cannot be undone.</p>
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
const s2Opts = { theme: 'bootstrap-5' };

// ── Initialize Select2 inside modals with dropdownParent ─────────────────────
// Must use dropdownParent so the dropdown renders above the modal backdrop.
$('#addModal').on('shown.bs.modal', function () {
    $('#addFromCurrency, #addToCurrency', this).each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            window.initS2Code($(this));
        }
    });
    updateAddLabel();
});

$('#editModal').on('shown.bs.modal', function () {
    $('#editFromCurrency, #editToCurrency', this).each(function () {
        if (!$(this).hasClass('select2-hidden-accessible')) {
            window.initS2Code($(this));
        }
    });
    updateEditLabel();
});

// ── Rate label helpers ────────────────────────────────────────────────────────
function updateAddLabel() {
    document.getElementById('addRatePrefix').textContent = '1 ' + $('#addFromCurrency').val() + ' =';
    document.getElementById('addRateSuffix').textContent = $('#addToCurrency').val();
}
function updateEditLabel() {
    document.getElementById('editRatePrefix').textContent = '1 ' + $('#editFromCurrency').val() + ' =';
    document.getElementById('editRateSuffix').textContent = $('#editToCurrency').val();
}

// jQuery change events — Select2 fires these reliably
$('#addFromCurrency, #addToCurrency').on('change', updateAddLabel);
$('#editFromCurrency, #editToCurrency').on('change', updateEditLabel);

// ── Edit modal ────────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editDate').value  = btn.dataset.date;
        document.getElementById('editRate').value  = btn.dataset.rate;
        document.getElementById('editNotes').value = btn.dataset.notes;
        document.getElementById('editForm').action =
            '{{ url("masters/exchange-rates") }}/' + btn.dataset.id;
        // Set Select2 values via jQuery so the display updates correctly
        $('#editFromCurrency').val(btn.dataset.from).trigger('change');
        $('#editToCurrency').val(btn.dataset.to).trigger('change');
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});

// ── Delete modal ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/exchange-rates") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
