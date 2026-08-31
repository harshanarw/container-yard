@extends('layouts.app')

{{--
    One screen, two pricing modes.

    Tariff mode resolves every rate from the customer's agreed tariff and refuses
    to save when one is missing. Manual mode resolves none of them: the operator
    types the free time and the rates, and the charge codes are fixed so the tax
    treatment and the accounts are still not up for negotiation.

    They share this file rather than forking it because almost everything is the
    same — the parameters, the container load, the tax arithmetic, the totals, the
    save. Only the rate columns and what blocks the save differ, so those are the
    only places `manual` appears.
--}}
@php($manual = $manual ?? false)

@section('title', $manual ? 'Generate Storage & Handling Invoice — Manual' : 'Generate Storage & Handling Invoice')

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('billing.storage-handling.index') }}" class="text-decoration-none">Storage &amp; Handling</a>
    </li>
    <li class="breadcrumb-item active">{{ $manual ? 'Generate Invoice — Manual' : 'Generate Invoice' }}</li>
@endsection

@push('styles')
<style>
    .summary-card {
        border-radius: 10px;
        background: linear-gradient(135deg, #0f5132 0%, #1a8a58 100%);
        color: #fff;
    }
    .summary-card .label { opacity: .75; font-size: .78rem; }
    #previewTable th, #previewTable td { font-size: .8rem; padding: .35rem .55rem; }
    .badge-size { font-size: .78rem; letter-spacing: .04em; }
    .handling-yes  { color: #0d6efd; font-weight: 600; }
    .handling-no   { color: #adb5bd; }
    #billingPartyBox { border-left: 3px solid #0d6efd; }

    /* Manual pricing: rate boxes and the matrix that fills them */
    .rate-input {
        width: 6.5rem; text-align: right; font-size: .78rem;
        padding: .15rem .35rem; border: 1px solid #ced4da; border-radius: .25rem;
    }
    .rate-input:focus { outline: 0; border-color: #0d6efd; box-shadow: 0 0 0 .15rem rgba(13,110,253,.2); }
    /* An overridden line is visibly not following the matrix, so a later reviewer
       can tell a deliberate exception from a typo. */
    .rate-input.overridden { border-color: #fd7e14; background: #fff8f0; font-weight: 600; }
    .rate-input.blank      { border-color: #dc3545; background: #fff5f5; }
    #matrixTable th, #matrixTable td { font-size: .78rem; padding: .3rem .5rem; vertical-align: middle; }

    /* Excluded from this invoice — visible so it can be put back, faded so it
       reads as "not on the bill" rather than as a zero-value line. */
    tr.line-excluded > td { opacity: .45; }
    tr.line-excluded > td:first-child { opacity: 1; }
    tr.line-excluded .rate-input { text-decoration: line-through; }
</style>
@endpush

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4>
            <i class="bi {{ $manual ? 'bi-pencil-square me-2 text-warning' : 'bi-file-earmark-plus me-2 text-primary' }}"></i>
            Generate Storage &amp; Handling Invoice
            @if($manual)
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle align-middle ms-1"
                      style="font-size:.7rem;letter-spacing:.04em;">MANUAL PRICING</span>
            @endif
        </h4>
        <p class="text-muted mb-0 small">
            @if($manual)
                Free time and all rates are entered by hand — the customer's tariff is not consulted.
                Charge codes, tax and posting are unchanged.
            @else
                Calculates storage charges plus Lift Off (Gate In) and Lift On (Gate Out) handling for the selected period
            @endif
        </p>
    </div>
    <a href="{{ route('billing.storage-handling.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<form id="billingForm" method="POST" action="{{ route('billing.storage-handling.store') }}">
@csrf
@if($manual)
    {{-- store() reads this to pick its guard; it also re-checks the permission. --}}
    <input type="hidden" name="pricing_mode" value="manual">
@endif

<div class="row g-3">

    {{-- ── Left: Parameters ──────────────────────────────────────────────── --}}
    <div class="col-lg-4">

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-sliders me-2 text-primary"></i>Invoice Parameters
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Bill Type <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group" id="billTypeGroup">
                        <input type="radio" class="btn-check" name="bill_type" id="btStorageHandling" value="storage_handling" autocomplete="off" {{ ($billType ?? 'storage_handling') === 'storage_handling' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary btn-sm" for="btStorageHandling">Storage &amp; Handling</label>
                        <input type="radio" class="btn-check" name="bill_type" id="btStorageOnly" value="storage_only" autocomplete="off" {{ ($billType ?? '') === 'storage_only' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary btn-sm" for="btStorageOnly">Storage Only</label>
                        <input type="radio" class="btn-check" name="bill_type" id="btHandlingOnly" value="handling_only" autocomplete="off" {{ ($billType ?? '') === 'handling_only' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary btn-sm" for="btHandlingOnly">Handling Only</label>
                    </div>
                    <div class="form-text">Storage &amp; Handling bills both sections; the others skip the part that doesn't apply.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Shipping Line / Operator <span class="text-danger">*</span>
                    </label>
                    <select name="shipping_line_id" id="shippingLineId" class="form-select select2 s2-code" required data-s2-sel="name">
                        <option value="">— Select Operator —</option>
                        @foreach($shippingLines as $sl)
                            <option value="{{ $sl->id }}"
                                    data-code="{{ $sl->code }}" data-name="{{ $sl->name }}"
                                    data-tax-exempt="{{ $sl->tax_exempt ? '1' : '0' }}"
                                    data-billing-party-id="{{ $sl->billing_party_id ?? '' }}"
                                    data-billing-party-name="{{ $sl->billingParty->name ?? '' }}"
                                    data-billing-party-address="{{ $sl->billingParty->address ?? '' }}">
                                [{{ $sl->code }}] {{ $sl->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($shippingLines->isEmpty())
                        <div class="form-text text-warning">
                            No active shipping line / operator customers found. Add one under Customers first.
                        </div>
                    @endif
                </div>

                <!-- Billing Party (searchable dropdown, auto-set from Customer master, overridable) -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Billing Party</label>
                    <select name="billing_party_id" id="billingPartyId" class="form-select select2 s2-code" data-s2-sel="name">
                        <option value="">— Select Billing Party —</option>
                        @foreach($allCustomers as $c)
                            <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" data-address="{{ $c->address ?? '' }}">
                                [{{ $c->code }}] {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                    <div id="billingPartyInfo" class="px-2 py-1 rounded small mt-1 d-none" style="border-left:3px solid #0d6efd;background:#f8f9ff;">
                        <span class="text-muted" id="billingPartyAddress" style="font-size:.78rem;"></span>
                    </div>
                </div>

                <div id="taxExemptAlert" class="alert alert-warning py-2 small d-none mb-2">
                    <i class="bi bi-shield-check me-1"></i>
                    <strong>Tax Exempt Customer</strong> — all tax rates will be applied as 0%.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Invoice Type <span class="text-danger">*</span></label>
                    <select name="invoice_type" id="invoiceType" class="form-select" required>
                        <option value="tax_invoice">Tax Invoice</option>
                        <option value="invoice">Invoice</option>
                        <option value="debit_note">Debit Note</option>
                    </select>
                </div>

                @php
                    $todayRate = \App\Models\ExchangeRate::getRate('USD', 'LKR', date('Y-m-d'));
                @endphp
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Invoice Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" name="invoice_date" id="invoiceDate" class="form-control"
                           value="{{ date('Y-m-d') }}" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label fw-semibold">Invoice Currency <span class="text-danger">*</span></label>
                        <select id="invoiceCurrency" class="form-select s2-code" data-s2-sel="name">
                            <option value="LKR" data-code="LKR" data-name="Sri Lankan Rupee" selected>LKR — Sri Lankan Rupee</option>
                            <option value="USD" data-code="USD" data-name="US Dollar">USD — US Dollar</option>
                        </select>
                        <div class="form-text">Stored in LKR; display uses the USD→LKR rate above. (Tariffs are USD/LKR, so only these are supported.)</div>
                    </div>
                    <div class="col-7">
                        <label class="form-label fw-semibold">
                            <span id="rateLabel">USD → LKR Rate</span> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text small" id="ratePrefixLabel">1 USD =</span>
                            <input type="number" id="exchangeRate" class="form-control"
                                   value="{{ $todayRate ? number_format((float)$todayRate, 4, '.', '') : '1.0000' }}"
                                   min="0.0001" step="0.0001" placeholder="e.g. 300">
                            <span class="input-group-text" id="rateSuffixLabel">LKR</span>
                        </div>
                        <div class="form-text d-flex align-items-center gap-1">
                            @if($todayRate)
                                <span id="rateNote" class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>Rate auto-loaded: 1 USD = {{ number_format((float)$todayRate, 4) }} LKR
                                </span>
                            @else
                                <span id="rateNote" class="text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>No rate found for today — please enter manually or add in Exchange Rate master
                                </span>
                            @endif
                            <span id="rateSpinner" class="spinner-border spinner-border-sm d-none" style="width:.75rem;height:.75rem;"></span>
                        </div>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">
                            Period From <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="period_from" id="periodFrom" class="form-control"
                               value="{{ date('Y-m-01') }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">
                            Period To <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="period_to" id="periodTo" class="form-control"
                               value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                @if($manual)
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Free Time (days) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <input type="number" name="manual_free_days" id="manualFreeDays" class="form-control"
                               value="0" min="0" max="9999" step="1" required>
                        <span class="input-group-text small">days</span>
                    </div>
                    <div class="form-text small">
                        Applies to every line. Free time is spent from each container's original gate-in,
                        not granted again each period — a box that used its allowance in an earlier period
                        gets none here.
                    </div>
                </div>
                @endif

                <div class="mb-3">
                    <label class="form-label fw-semibold">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"
                              placeholder="Internal notes for this invoice…"></textarea>
                </div>

                <div class="d-grid">
                    <button type="button" id="previewBtn" class="btn btn-primary">
                        <i class="bi bi-eye me-2"></i>Preview Charges
                    </button>
                </div>

            </div>
        </div>

        {{-- Tariff alert box --}}
        <div id="tariffAlert" class="d-none"></div>
        <div id="missingRatesPanel" class="d-none"></div>

        {{-- Charge type legend --}}
        <div class="card content-card">
            <div class="card-body py-2 small">
                <div class="fw-semibold mb-2 text-muted">Charge Types</div>
                <div class="d-flex align-items-center mb-1">
                    <i class="bi bi-arrow-down-circle text-success me-2"></i>
                    <div><strong>Lift Off</strong> — Gate In event during the period</div>
                </div>
                <div class="d-flex align-items-center mb-1">
                    <i class="bi bi-arrow-up-circle text-primary me-2"></i>
                    <div><strong>Lift On</strong> — Gate Out event during the period</div>
                </div>
                <div class="d-flex align-items-center">
                    <i class="bi bi-building text-warning me-2"></i>
                    <div><strong>Storage</strong> — Days in yard × daily rate</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Right: Preview ─────────────────────────────────────────────────── --}}
    <div class="col-lg-8">

        {{-- Summary card (hidden until preview) --}}
        <div id="summarySection" class="d-none">

            <div class="summary-card p-4 mb-3">
                <div class="row g-2 text-center">
                    <div class="col-3">
                        <div class="label">Containers</div>
                        <div class="fs-3 fw-bold" id="sumContainers">0</div>
                    </div>
                    <div class="col-3" id="storageTile">
                        <div class="label">Storage</div>
                        <div class="fs-5 fw-bold" id="sumStorage">0.00</div>
                    </div>
                    <div class="col-3" id="handlingTile">
                        <div class="label">Handling</div>
                        <div class="fs-5 fw-bold" id="sumHandling">0.00</div>
                    </div>
                    <div class="col-3">
                        <div class="label">Subtotal</div>
                        <div class="fs-5 fw-bold" id="sumSubtotal">0.00</div>
                    </div>
                    <div class="col-12 border-top border-white border-opacity-25 pt-2 mt-1">
                        <div class="row">
                            <div class="col-6">
                                <div class="label">SSCL</div>
                                <div class="fs-5 fw-bold" id="sumSscl">0.00</div>
                            </div>
                            <div class="col-6">
                                <div class="label">VAT</div>
                                <div class="fs-5 fw-bold" id="sumVat">0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 border-top border-white border-opacity-25 pt-2">
                        <div class="label">Total Invoice Amount</div>
                        <div class="display-5 fw-bold" id="sumTotal">0.00</div>
                    </div>
                </div>
            </div>

            @if($manual)
            {{-- Rate matrix — one row per equipment type × size the period actually
                 contains, so nobody is asked for a rate that will not be used.
                 Typing here fills every matching line below. --}}
            <div class="card content-card mb-3" id="rateMatrixCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-grid-3x3-gap me-2 text-warning"></i>
                        <strong>Rate Matrix</strong>
                        <span class="text-muted small ms-2">— fills every matching line; individual lines can still be overridden</span>
                    </span>
                    <span id="matrixCount" class="badge bg-warning-subtle text-warning border border-warning-subtle"></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="matrixTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-2">Equipment</th>
                                    <th class="text-center">Size</th>
                                    <th class="text-end">Storage / Day</th>
                                    <th class="text-end">Lift Off</th>
                                    <th class="text-end">Lift On</th>
                                    <th class="text-end pe-2 text-muted" style="font-size:.7rem;">Lines</th>
                                </tr>
                            </thead>
                            <tbody id="matrixBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Section 1: Storage Charges --}}
            <div class="card content-card mb-3" id="storageCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-building me-2 text-warning"></i>
                        <strong>Storage Charges</strong>
                    </span>
                    <span id="lineCount" class="badge bg-warning-subtle text-warning border border-warning-subtle"></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="storageTable">
                            <thead class="table-light">
                                @if($manual)
                                {{-- No tariff rate, no currency conversion: the operator
                                     types the rate in the invoice's own currency, so the
                                     four-column breakdown has nothing to break down. --}}
                                <tr>
                                    {{-- Unticking drops a container from the bill. It stays on
                                         screen, greyed, so it can be put back. --}}
                                    <th class="ps-2 text-center" style="width:2.2rem;">
                                        <input type="checkbox" class="form-check-input select-all" id="selAllStorage"
                                               data-table="storage" checked title="Select / clear all">
                                    </th>
                                    <th>#</th>
                                    <th>Container</th>
                                    <th class="text-center">Size</th>
                                    <th>Equipment</th>
                                    <th>Status</th>
                                    <th>Gate In</th>
                                    <th class="text-center">From</th>
                                    <th class="text-center">To</th>
                                    <th class="text-center">Days</th>
                                    <th class="text-center">Free</th>
                                    <th class="text-center">Chgbl</th>
                                    <th class="text-end bg-warning-subtle">Rate / Day</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end pe-2 text-muted" style="font-size:.7rem;white-space:nowrap;">Value<br>(LKR)</th>
                                </tr>
                                @else
                                <tr>
                                    <th class="ps-2" rowspan="2" style="vertical-align:middle;">#</th>
                                    <th rowspan="2" style="vertical-align:middle;">Container</th>
                                    <th class="text-center" rowspan="2" style="vertical-align:middle;">Size</th>
                                    <th rowspan="2" style="vertical-align:middle;">Equipment</th>
                                    <th rowspan="2" style="vertical-align:middle;">Status</th>
                                    <th rowspan="2" style="vertical-align:middle;">Gate In</th>
                                    <th class="text-center" rowspan="2" style="vertical-align:middle;">From</th>
                                    <th class="text-center" rowspan="2" style="vertical-align:middle;">To</th>
                                    <th class="text-center" rowspan="2" style="vertical-align:middle;">Days</th>
                                    <th class="text-center" rowspan="2" style="vertical-align:middle;">Free</th>
                                    <th class="text-center" rowspan="2" style="vertical-align:middle;">Chgbl</th>
                                    <th colspan="4" class="text-center bg-warning-subtle" style="border-bottom:1px solid #dee2e6;font-size:.7rem;letter-spacing:.04em;">
                                        Daily Rate Breakdown
                                    </th>
                                    <th class="text-end" rowspan="2" style="vertical-align:middle;">Amount</th>
                                    <th class="text-end pe-2 text-muted" rowspan="2" style="vertical-align:middle;font-size:.7rem;white-space:nowrap;">Value<br>(LKR)</th>
                                </tr>
                                <tr>
                                    <th class="text-end bg-warning-subtle" style="font-size:.7rem;">Tariff Rate</th>
                                    <th class="text-center bg-warning-subtle" style="font-size:.7rem;">Cur</th>
                                    <th class="text-end bg-warning-subtle" style="font-size:.7rem;">× Exch. Rate</th>
                                    <th class="text-end bg-warning-subtle" style="font-size:.7rem;">Rate / Day</th>
                                </tr>
                                @endif
                            </thead>
                            <tbody id="storageBody"></tbody>
                            <tfoot id="storageFoot" class="table-light fw-semibold"></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Section 2: Handling Charges --}}
            <div class="card content-card mb-3" id="handlingCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-truck me-2 text-info"></i>
                        <strong>Handling Charges</strong>
                    </span>
                    <span id="handlingCount" class="badge bg-info-subtle text-info border border-info-subtle"></span>
                </div>
                <div class="card-body p-0">

                    {{-- Lift Off --}}
                    <div class="px-3 pt-2 pb-1 bg-success-subtle border-bottom">
                        <span class="small fw-bold text-success">
                            <i class="bi bi-arrow-down-circle me-1"></i>Lift Off
                        </span>
                        <span class="text-muted small ms-1">— Gate In events during billing period</span>
                    </div>
                    <div id="liftOffSection">
                        <div class="px-3 py-2 text-muted small fst-italic">No lift-off events.</div>
                    </div>

                    {{-- Lift On --}}
                    <div class="px-3 pt-2 pb-1 bg-primary-subtle border-top border-bottom">
                        <span class="small fw-bold text-primary">
                            <i class="bi bi-arrow-up-circle me-1"></i>Lift On
                        </span>
                        <span class="text-muted small ms-1">— Gate Out events during billing period</span>
                    </div>
                    <div id="liftOnSection">
                        <div class="px-3 py-2 text-muted small fst-italic">No lift-on events.</div>
                    </div>

                    <div class="px-3 py-2 bg-info-subtle border-top fw-semibold d-flex justify-content-between">
                        <span class="text-info small">
                            <i class="bi bi-truck me-1"></i>Handling Subtotal
                        </span>
                        <span id="handlingSubtotalFooter">—</span>
                    </div>
                </div>
            </div>

            {{-- Section 3: Invoice Total --}}
            <div class="card content-card mb-3">
                <div class="card-header">
                    <i class="bi bi-receipt me-2 text-primary"></i><strong>Invoice Total</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0" id="totalTable"></table>
                </div>
            </div>

            {{-- Save --}}
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('billing.storage-handling.index') }}"
                   class="btn btn-outline-secondary">
                    <i class="bi bi-x me-1"></i>Cancel
                </a>
                <button type="submit" id="saveBtn" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>Save Invoice
                </button>
            </div>
        </div>

        {{-- Placeholder --}}
        <div id="previewPlaceholder" class="card content-card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-file-earmark-ruled fs-1 d-block mb-3 text-primary opacity-25"></i>
                <p class="mb-1">
                    Select a shipping line / operator and billing period,<br>
                    then click <strong>Preview Charges</strong>.
                </p>
                <p class="small">
                    The preview will show storage charges for all containers in yard during the period
                    plus Lift Off / Lift On charges for gate movements that occurred within the period.
                </p>
            </div>
        </div>

    </div>
</div>

</form>

@endsection

@push('scripts')
<script>
const csrfToken   = '{{ csrf_token() }}';
const MANUAL      = @json($manual);
const previewUrl  = MANUAL
    ? '{{ route("billing.storage-handling.manual.preview") }}'
    : '{{ route("billing.storage-handling.preview") }}';
const exchRateUrl = @json(route('finance.fx-rate'));

let previewLines = [];
let previewMissing = [];
// Rate matrix rows, and the rates typed into them, keyed by matrix_key.
let rateMatrix   = [];
let matrixRates  = {};
// Currency context of the current preview, used by every recalculation.
let previewCur   = { inv: 'LKR', def: 'LKR', ex: 1 };

function fmtEqt(l) {
    if (!l.eqt_code) return l.equipment_type || '—';
    const isReefer = l.type_code && ['RF','RH'].includes(l.type_code);
    const chip = '<span class="badge ' + (isReefer ? 'badge-reefer' : 'bg-dark') + '" style="font-size:.72rem;">' + l.eqt_code + '</span>';
    return chip + (l.iso_code ? ' <span class="badge bg-secondary-subtle text-secondary border" style="font-size:.65rem;">' + l.iso_code + '</span>' : '');
}

// Fetch USD → LKR exchange rate from master based on invoice date.
// Always fetches USD→LKR regardless of invoice currency, because tariffs may be
// USD-denominated even when the invoice is issued in LKR.
async function fetchExchangeRate() {
    const date = document.getElementById('invoiceDate').value;

    document.getElementById('rateLabel').textContent       = 'USD → LKR Rate';
    document.getElementById('ratePrefixLabel').textContent = '1 USD =';
    document.getElementById('rateSuffixLabel').textContent = 'LKR';

    if (!date) return;

    const spinner = document.getElementById('rateSpinner');
    spinner.classList.remove('d-none');
    document.getElementById('rateNote').textContent = 'Looking up rate…';

    try {
        const res  = await fetch(exchRateUrl + '?currency=USD&date=' + encodeURIComponent(date));
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        const note = document.getElementById('rateNote');
        if (data.found && data.rate != null) {
            const r = parseFloat(data.rate).toFixed(4);
            document.getElementById('exchangeRate').value = r;
            note.className = 'text-success';
            note.innerHTML = '<i class="bi bi-check-circle me-1"></i>Rate auto-loaded: 1 USD = ' + r + ' LKR';
        } else {
            note.className = 'text-warning';
            note.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>No rate found for ' + date + ' — please enter manually';
        }
    } catch (e) {
        const note = document.getElementById('rateNote');
        note.className = 'text-danger';
        note.innerHTML = '<i class="bi bi-x-circle me-1"></i>Could not fetch rate — please enter manually';
    } finally {
        spinner.classList.add('d-none');
    }
}

// Re-fetch rate when invoice date changes.
// Flatpickr (without altInput) fires native 'change' on the original input
// when a date is selected from the calendar, so one listener covers both.
function onDateChange() {
    const val = document.getElementById('invoiceDate').value;
    if (val && /^\d{4}-\d{2}-\d{2}$/.test(val)) fetchExchangeRate();
}
// Operator selection: auto-set invoice type, billing party, tax-exempt alert
function onShippingLineChange() {
    const val    = $('#shippingLineId').val();
    const $opt   = $('#shippingLineId').find('option[value="' + val + '"]');
    const exempt = $opt.attr('data-tax-exempt') === '1';

    document.getElementById('taxExemptAlert').classList.toggle('d-none', !exempt);
    document.getElementById('invoiceType').value = exempt ? 'invoice' : 'tax_invoice';

    const bpId = val ? ($opt.attr('data-billing-party-id') || val) : '';
    if (bpId) {
        $('#billingPartyId').val(bpId).trigger('change');
    }
}
// Billing party: show address info panel on selection
function onBillingPartyChange() {
    const val    = $('#billingPartyId').val();
    const $opt   = $('#billingPartyId').find('option[value="' + val + '"]');
    const addr   = val ? ($opt.attr('data-address') || '') : '';
    const infoEl = document.getElementById('billingPartyInfo');
    const addrEl = document.getElementById('billingPartyAddress');
    if (val && addr) {
        addrEl.textContent = addr;
        infoEl.classList.remove('d-none');
    } else {
        addrEl.textContent = '';
        infoEl.classList.add('d-none');
    }
}
function selectedBillType() {
    return document.querySelector('input[name="bill_type"]:checked')?.value || 'storage_handling';
}
async function runPreview() {
    const shippingLineId  = document.getElementById('shippingLineId').value;
    const periodFrom      = document.getElementById('periodFrom').value;
    const periodTo        = document.getElementById('periodTo').value;
    const invoiceCurrency = document.getElementById('invoiceCurrency').value;
    const exchangeRate    = parseFloat(document.getElementById('exchangeRate').value || 1);
    const billType        = selectedBillType();

    if (!shippingLineId) { showToast('Please select a shipping line / operator.', 'warning'); return; }
    if (!periodFrom || !periodTo) { showToast('Please enter the billing period dates.', 'warning'); return; }

    const btn = document.getElementById('previewBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading…';

    try {
        const res = await fetch(previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                bill_type:        billType,
                shipping_line_id: shippingLineId,
                period_from:      periodFrom,
                period_to:        periodTo,
                invoice_currency: invoiceCurrency,
                exchange_rate:    exchangeRate,
                manual_free_days: MANUAL ? headerFreeDays() : null,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            showAlert('danger', data.message || 'Preview failed. Please check your inputs.');
            return;
        }

        renderPreview(data);

    } catch (e) {
        showAlert('danger', 'Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-eye me-2"></i>Preview Charges';
    }
}

// ── Manual pricing ───────────────────────────────────────────────────────────
// Everything below mirrors App\Services\Billing\ManualPricing deliberately. The
// operator has to see the total move as they type, and the server recomputes the
// same numbers on save because nothing posted from a browser is trusted — so the
// two must agree. If you change a rule here, change it there.

const r2  = n => Math.round((parseFloat(n) || 0) * 100) / 100;
// A rate the operator actually typed. Blank is "not entered"; an explicit 0 is a
// value — occasionally a deliberate goodwill line.
const typed = v => (v !== null && v !== undefined && v !== '' && !isNaN(parseFloat(v))) ? parseFloat(v) : null;

function headerFreeDays() {
    const el = document.getElementById('manualFreeDays');
    return el ? Math.max(0, parseInt(el.value, 10) || 0) : 0;
}

// ── Which containers are on this bill ────────────────────────────────────────
// The period load returns every container the customer had in the yard; some do
// not belong on this invoice. Selection is screen state, never invoice data — an
// unticked container simply has no line, which is why the view and print screens
// need to know nothing about it.

/** Lines default to on: the common case is billing everything that came back. */
const selected = l => l._selected !== false;

const selectedLines = () => previewLines.filter(selected);

/**
 * One checkbox cell. A container shown in two tables gets two of these, and they
 * are not two switches — they are two pictures of one flag, kept in step by the
 * repaint in recalcAll().
 */
function pickCell(prefix, idx, table) {
    return `<td class="text-center">
        <input type="checkbox" class="form-check-input line-pick" id="${prefix}-${idx}"
               data-line="${idx}" data-table="${table}" checked>
    </td>`;
}

/** Tick or untick every line drawn in one table. */
function setTableSelection(table, on) {
    const applies = l =>
        table === 'storage'  ? true :
        table === 'lift_off' ? !!l.has_lift_off :
                               !!l.has_lift_on;

    previewLines.forEach(l => { if (applies(l)) l._selected = on; });
    recalcAll();
}

// Free time is spent from the container's original gate-in, not granted afresh
// each period. Mirrors ManualPricing::freeDaysInPeriod().
function freeDaysInPeriod(headerFree, daysBefore, totalDays) {
    const remaining = Math.max(0, headerFree - Math.max(0, daysBefore));
    return Math.min(Math.max(0, totalDays), remaining);
}

// The rate a line follows: its own override if one was typed, otherwise the
// matrix row for its equipment type × size.
function rateFor(line, kind) {
    const own = typed(line['_ovr_' + kind]);
    if (own !== null) return own;
    return typed((matrixRates[line.matrix_key] || {})[kind]);
}

function isOverridden(line, kind) {
    return typed(line['_ovr_' + kind]) !== null;
}

// Recompute one line end to end: free days, rates, subtotals, tax, totals.
function recalcLine(line) {
    const free  = headerFreeDays();
    const total = parseInt(line.storage_total_days, 10) || 0;

    const freeIn = freeDaysInPeriod(free, parseInt(line.days_before_period, 10) || 0, total);
    line.storage_free_days       = freeIn;
    line.storage_chargeable_days = Math.max(0, total - freeIn);

    const sRate  = rateFor(line, 'storage');
    const loRate = rateFor(line, 'lift_off');
    const lnRate = rateFor(line, 'lift_on');

    // Blank stays blank so the save guard can name the container rather than
    // silently billing zero.
    line.storage_daily_rate = sRate === null ? '' : sRate;
    line.lift_off_rate      = loRate === null ? '' : loRate;
    line.lift_on_rate       = lnRate === null ? '' : lnRate;

    line.storage_subtotal  = r2(line.storage_chargeable_days * (sRate || 0));
    line.handling_subtotal = r2((line.has_lift_off ? (loRate || 0) : 0) + (line.has_lift_on ? (lnRate || 0) : 0));

    // Storage and handling are taxed separately — they carry different charge
    // codes, so one code's rates must not touch the other's money.
    const sSscl = r2(line.storage_subtotal * (parseFloat(line.tax1_rate) || 0) / 100);
    const sVat  = r2((line.storage_subtotal + sSscl) * (parseFloat(line.tax2_rate) || 0) / 100);
    const hSscl = r2(line.handling_subtotal * (parseFloat(line.handling_tax1_rate) || 0) / 100);
    const hVat  = r2((line.handling_subtotal + hSscl) * (parseFloat(line.handling_tax2_rate) || 0) / 100);

    line.line_total        = r2(line.storage_subtotal + line.handling_subtotal);
    line.line_sscl         = r2(sSscl + hSscl);
    line.line_vat          = r2(sVat + hVat);
    line.line_grand_total  = r2(line.line_total + line.line_sscl + line.line_vat);
    line.line_value        = line.line_grand_total;

    const disp = previewCur.inv === previewCur.def ? 1 : (previewCur.ex > 0 ? 1 / previewCur.ex : 1);
    line.line_amount = r2(line.line_grand_total * disp);
}

/** Chargeable positions with no rate typed. Blocks the save, naming containers. */
function manualBlockers() {
    const groups = {};
    const add = (op, line) => {
        const key = op + '|' + line.matrix_key;
        (groups[key] = groups[key] || { operation: op, equipment: line.eqt_code, size: line.container_size, containers: [] })
            .containers.push(line.container_no);
    };
    // Only lines that will actually be saved. An excluded container with a blank
    // rate must not block a save it is not part of.
    selectedLines().forEach(l => {
        if (l.storage_chargeable_days > 0 && rateFor(l, 'storage')  === null) add('storage',  l);
        if (l.has_lift_off            && rateFor(l, 'lift_off') === null) add('lift-off', l);
        if (l.has_lift_on             && rateFor(l, 'lift_on')  === null) add('lift-on',  l);
    });
    return Object.values(groups);
}

/** An invoice with no containers on it is not an invoice. */
const nothingSelected = () => previewLines.length > 0 && selectedLines().length === 0;

function renderManualBlockers() {
    const panel = document.getElementById('missingRatesPanel');

    if (nothingSelected()) {
        panel.className = 'alert alert-danger d-flex align-items-center gap-2 mb-3';
        panel.innerHTML = '<i class="bi bi-exclamation-octagon-fill"></i> '
            + '<div><strong>No containers selected.</strong> Tick at least one container to bill.</div>';
        return true;
    }

    const blockers = manualBlockers();

    if (!blockers.length) {
        panel.className = 'd-none';
        panel.innerHTML = '';
        return false;
    }

    const label = { 'storage': 'Storage', 'lift-off': 'Lift Off', 'lift-on': 'Lift On' };
    const rows = blockers.map(b => {
        const shown = b.containers.slice(0, 6).join(', ')
            + (b.containers.length > 6 ? ' +' + (b.containers.length - 6) + ' more' : '');
        return `<tr>
            <td class="fw-semibold">${label[b.operation] || b.operation}</td>
            <td>${(b.equipment || '—')}${b.size ? " · " + b.size + "'" : ''}</td>
            <td class="small text-muted">${shown}</td>
            <td class="text-end">${b.containers.length}</td>
        </tr>`;
    }).join('');

    panel.className = 'alert alert-danger mb-3';
    panel.innerHTML = `
        <div class="d-flex align-items-start gap-2 mb-2">
            <i class="bi bi-exclamation-octagon-fill mt-1"></i>
            <div><strong>Cannot save — rates missing.</strong>
            Fill the rate matrix above, or type a rate on each line listed.</div>
        </div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle small">
            <thead><tr><th>Charge</th><th>Combination</th><th>Containers</th><th class="text-end">Count</th></tr></thead>
            <tbody>${rows}</tbody></table></div>`;
    return true;
}

function renderMatrix() {
    const body = document.getElementById('matrixBody');
    if (!body) return;

    document.getElementById('matrixCount').textContent =
        rateMatrix.length + (rateMatrix.length === 1 ? ' combination' : ' combinations');

    const box = (key, kind, count) => count === 0
        ? '<span class="text-muted small">—</span>'
        : `<input type="number" class="rate-input" min="0" step="0.01" data-matrix="${key}" data-kind="${kind}"
                  value="${matrixRates[key] && matrixRates[key][kind] !== undefined ? matrixRates[key][kind] : ''}"
                  placeholder="0.00">`;

    body.innerHTML = rateMatrix.map((m, n) => `
        <tr id="matrixRow-${n}" data-key="${m.key}">
            <td class="ps-2">${fmtEqt(m)}</td>
            <td class="text-center"><span class="badge bg-dark badge-size">${m.container_size || '—'}'</span></td>
            <td class="text-end">${box(m.key, 'storage',  m.storage_lines)}</td>
            <td class="text-end">${box(m.key, 'lift_off', m.lift_off_lines)}</td>
            <td class="text-end">${box(m.key, 'lift_on',  m.lift_on_lines)}</td>
            <td class="text-end pe-2 text-muted small" id="matrixCount-${n}">${m.lines}</td>
        </tr>
    `).join('');

    body.querySelectorAll('input[data-matrix]').forEach(inp => {
        inp.addEventListener('input', function () {
            const key = this.dataset.matrix, kind = this.dataset.kind;
            matrixRates[key] = matrixRates[key] || {};
            matrixRates[key][kind] = this.value;
            recalcAll();
        });
    });
}

/**
 * Matrix rows count the lines they actually feed, so excluding containers is
 * visible here too — a row down to zero has nothing left to price.
 */
function updateMatrixCounts(onBill) {
    rateMatrix.forEach((m, n) => {
        const mine = onBill.filter(l => l.matrix_key === m.key).length;
        const cell = document.getElementById('matrixCount-' + n);
        const row  = document.getElementById('matrixRow-' + n);
        if (cell) cell.textContent = mine === m.lines ? m.lines : `${mine} of ${m.lines}`;
        if (row)  row.classList.toggle('line-excluded', mine === 0);
    });
}

/**
 * Recompute every line and repaint the cells that changed.
 *
 * Cells rather than whole tables: re-rendering would take the focus out of the
 * box the operator is typing in.
 */
function recalcAll() {
    previewLines.forEach(recalcLine);

    const invCur = previewCur.inv, defCur = previewCur.def, exRate = previewCur.ex;
    const fmt    = n => parseFloat(n || 0).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const toAmt  = lkr => invCur === defCur ? parseFloat(lkr || 0) : parseFloat(lkr || 0) / exRate;
    const fmtAmt = lkr => invCur + '\xa0' + fmt(toAmt(lkr));
    const fmtVal = lkr => 'LKR\xa0' + fmt(lkr);
    const set    = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };

    previewLines.forEach((l, i) => {
        const on = selected(l);

        set('sFree-' + i, l.storage_free_days + 'd');
        set('sChg-'  + i, l.storage_chargeable_days + 'd');
        // An excluded line shows a dash rather than a zero: it is not on this
        // bill, which is a different statement from costing nothing.
        set('sAmt-'  + i, on ? fmtAmt(l.storage_subtotal)   : '—');
        set('sVal-'  + i, on ? fmtVal(l.storage_subtotal)   : '—');
        set('loAmt-' + i, on ? fmtAmt(l.lift_off_rate || 0) : '—');
        set('loVal-' + i, on ? fmtVal(l.lift_off_rate || 0) : '—');
        set('lnAmt-' + i, on ? fmtAmt(l.lift_on_rate  || 0) : '—');
        set('lnVal-' + i, on ? fmtVal(l.lift_on_rate  || 0) : '—');

        // Every picture of the flag, in whichever tables this line is drawn.
        ['selS-', 'selLo-', 'selLn-'].forEach(prefix => {
            const box = document.getElementById(prefix + i);
            if (box) box.checked = on;
        });
        ['sRow-', 'loRow-', 'lnRow-'].forEach(prefix => {
            const row = document.getElementById(prefix + i);
            if (row) row.classList.toggle('line-excluded', !on);
        });

        // Boxes the operator is not currently editing follow the matrix; the
        // styling says at a glance which lines are exceptions and which are blank.
        [['storage', 'sRate-'], ['lift_off', 'loRate-'], ['lift_on', 'lnRate-']].forEach(([kind, prefix]) => {
            const el = document.getElementById(prefix + i);
            if (!el) return;
            const override = isOverridden(l, kind);
            if (!override && document.activeElement !== el) {
                const v = rateFor(l, kind);
                el.value = v === null ? '' : v;
            }
            el.classList.toggle('overridden', override);
            el.classList.toggle('blank', rateFor(l, kind) === null && needsRate(l, kind));
        });
    });

    // Totals — over the lines that will actually be saved.
    const onBill = selectedLines();
    const sum = key => r2(onBill.reduce((s, l) => s + (parseFloat(l[key]) || 0), 0));
    const storageTotal  = sum('storage_subtotal');
    const handlingTotal = sum('handling_subtotal');
    const subtotal      = r2(storageTotal + handlingTotal);
    const ssclAmount    = sum('line_sscl');
    const vatAmount     = sum('line_vat');
    const totalAmount   = r2(subtotal + ssclAmount + vatAmount);

    set('sumStorage',  fmtAmt(storageTotal));
    set('sumHandling', fmtAmt(handlingTotal));
    set('sumSubtotal', fmtAmt(subtotal));
    set('sumSscl',     fmtAmt(ssclAmount));
    set('sumVat',      fmtAmt(vatAmount));
    set('sumTotal',    fmtAmt(totalAmount));

    document.getElementById('storageFoot').innerHTML = `
        <tr>
            <td colspan="13" class="text-end">Storage Subtotal</td>
            <td class="text-end">${fmtAmt(storageTotal)}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(storageTotal)}</td>
        </tr>`;

    const liftOffTotal = r2(onBill.filter(l => l.has_lift_off).reduce((s, l) => s + (parseFloat(l.lift_off_rate) || 0), 0));
    const liftOnTotal  = r2(onBill.filter(l => l.has_lift_on ).reduce((s, l) => s + (parseFloat(l.lift_on_rate)  || 0), 0));
    set('liftOffSubtotal', fmtAmt(liftOffTotal));
    set('liftOnSubtotal',  fmtAmt(liftOnTotal));
    set('handlingSubtotalFooter', fmtAmt(handlingTotal));

    // Counts follow the selection too — the operator should never have to count
    // rows to know what they are about to save.
    const total = previewLines.length, picked = onBill.length;
    const ofTotal = picked === total ? `${total}` : `${picked} of ${total}`;
    set('sumContainers', ofTotal);
    set('lineCount',     ofTotal + ' containers');
    set('handlingCount',
        `${onBill.filter(l => l.has_lift_off).length} lift-off · ${onBill.filter(l => l.has_lift_on).length} lift-on`);

    // A matrix row whose lines are all excluded feeds nothing.
    updateMatrixCounts(onBill);

    renderTotalTable({
        storage_subtotal: storageTotal, handling_subtotal: handlingTotal, subtotal: subtotal,
        sscl_amount: ssclAmount, vat_amount: vatAmount,
        total_amount: totalAmount, total_value: totalAmount,
    }, fmtAmt, fmtVal, invCur);

    const blocked = renderManualBlockers();
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.disabled = blocked;
        saveBtn.title = blocked ? 'Enter a rate for every chargeable line before saving' : '';
    }
}

/** Whether this line has something to price in this position at all. */
function needsRate(line, kind) {
    if (kind === 'storage')  return line.storage_chargeable_days > 0;
    if (kind === 'lift_off') return !!line.has_lift_off;
    return !!line.has_lift_on;
}

function renderPreview(data) {
    previewLines = data.lines || [];
    previewMissing = data.missing_rates || [];

    // Show only the sections relevant to the chosen bill type.
    const billType     = data.bill_type || 'storage_handling';
    const showStorage  = billType !== 'handling_only';
    const showHandling = billType !== 'storage_only';
    totalSections = { storage: showStorage, handling: showHandling };
    document.getElementById('storageTile').classList.toggle('d-none', !showStorage);
    document.getElementById('handlingTile').classList.toggle('d-none', !showHandling);
    document.getElementById('storageCard').classList.toggle('d-none', !showStorage);
    document.getElementById('handlingCard').classList.toggle('d-none', !showHandling);

    // Missing tariff rates → render the detail panel and block saving. Manual
    // mode has no tariff to be missing; recalcAll() blocks on blank rate boxes
    // instead, once the lines are on screen.
    if (!MANUAL) {
        const hasMissing = window.renderTariffMissing(document.getElementById('missingRatesPanel'), previewMissing);
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.disabled = hasMissing;
            saveBtn.title = hasMissing ? 'Resolve the missing tariff rates before saving' : '';
        }
    }

    // Tax exempt alert
    document.getElementById('taxExemptAlert').classList.toggle('d-none', !data.tax_exempt);

    // Tariff status alerts — only warn about a tariff for a section that is
    // actually being billed. On a storage-only or handling-only bill the other
    // section's tariff is never looked up (so its *_tariff_found flag is false by
    // design); warning about it would be misleading.
    const alertBox = document.getElementById('tariffAlert');

    if (MANUAL) {
        // No tariff was consulted, so there is nothing to report about one. What
        // does matter is the charge codes: without them a line has no tax
        // treatment and no account to post to, and saying so now beats
        // discovering it at save.
        const missingCodes = [];
        if (showStorage  && !data.storage_charge_code)  missingCodes.push('storage');
        if (showHandling && !data.handling_charge_code) missingCodes.push('handling');

        if (missingCodes.length) {
            alertBox.className = 'alert alert-danger mb-3';
            alertBox.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-1"></i> The default <strong>'
                + missingCodes.join(' and ') + '</strong> charge code is missing or inactive in the Charge Code master. '
                + 'Manual pricing takes its tax codes and accounts from there, so the invoice cannot be saved until it exists.';
        } else {
            const codes = [];
            if (showStorage)  codes.push('storage &rarr; <strong>' + data.storage_charge_code + '</strong>');
            if (showHandling) codes.push('handling &rarr; <strong>' + data.handling_charge_code + '</strong>');
            alertBox.className = 'alert alert-warning d-flex align-items-start gap-2 mb-3';
            alertBox.innerHTML = '<i class="bi bi-pencil-square mt-1"></i><div><strong>Manual pricing.</strong> '
                + 'No tariff was consulted — enter the rates below. Charge codes: ' + codes.join(', ') + '.</div>';
        }
        alertBox.classList.remove('d-none');
    } else {
        const msgs = [];
        if (showStorage && !data.storage_tariff_found) {
            msgs.push('<i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> No active <strong>storage tariff</strong> found for this shipping line. Rates from stored gate-in values will be used. <a href="{{ route("masters.storage-tariff.index") }}">Set up tariff &rarr;</a>');
        }
        if (showHandling && !data.handling_tariff_found) {
            msgs.push('<i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> No active <strong>handling tariff</strong> found for this shipping line — Lift On / Lift Off rates will be zero. <a href="{{ route("masters.handling-tariff.index") }}">Set up tariff &rarr;</a>');
        }

        if (msgs.length) {
            alertBox.className = 'alert alert-warning mb-3';
            alertBox.innerHTML = msgs.join('<hr class="my-2">');
        } else {
            const loaded = [];
            if (showStorage)  loaded.push('storage');
            if (showHandling) loaded.push('handling');
            const label = loaded.length === 2
                ? 'Both storage and handling tariffs'
                : (loaded[0].charAt(0).toUpperCase() + loaded[0].slice(1) + ' tariff');
            alertBox.className = 'alert alert-success d-flex align-items-center gap-2 mb-3';
            alertBox.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + label + ' loaded successfully.';
        }
        alertBox.classList.remove('d-none');
    }

    if (data.no_data || previewLines.length === 0) {
        alertBox.className = 'alert alert-info d-flex align-items-center gap-2 mb-3';
        alertBox.innerHTML = '<i class="bi bi-info-circle-fill"></i> No containers or gate movements found for this shipping line during the selected period.';
        alertBox.classList.remove('d-none');
        document.getElementById('summarySection').classList.add('d-none');
        document.getElementById('previewPlaceholder').classList.remove('d-none');
        return;
    }

    document.getElementById('previewPlaceholder').classList.add('d-none');
    document.getElementById('summarySection').classList.remove('d-none');

    // Currency context every later recalculation reads. Set before the tables so
    // the first recalcAll() formats against the same numbers they were drawn with.
    previewCur = {
        inv: data.invoice_currency || 'LKR',
        def: data.default_currency || 'LKR',
        ex:  parseFloat(data.exchange_rate) || 1,
    };

    const fmt    = n => parseFloat(n).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtCur = n => 'LKR\xa0' + fmt(n);
    const invCur = data.invoice_currency || 'LKR';
    const defCur = data.default_currency || 'LKR';
    const exRate = parseFloat(data.exchange_rate) || 1;

    // Amount = invoice-currency display; Value = default-currency (LKR) stored amount
    const toAmt  = lkr => invCur === defCur ? parseFloat(lkr) : parseFloat(lkr) / exRate;
    const fmtAmt = lkr => invCur + '\xa0' + fmt(toAmt(lkr));
    const fmtVal = lkr => fmtCur(lkr);

    // Summary card — show invoice-currency amounts
    document.getElementById('sumContainers').textContent = previewLines.length;
    document.getElementById('sumStorage').textContent    = fmtAmt(data.storage_subtotal);
    document.getElementById('sumHandling').textContent   = fmtAmt(data.handling_subtotal);
    document.getElementById('sumSubtotal').textContent   = fmtAmt(data.subtotal);
    document.getElementById('sumSscl').textContent       = fmtAmt(data.sscl_amount);
    document.getElementById('sumVat').textContent        = fmtAmt(data.vat_amount);
    document.getElementById('sumTotal').textContent      = fmtAmt(data.total_amount);
    document.getElementById('lineCount').textContent     = previewLines.length + ' containers';

    // ── Storage table ──────────────────────────────────────────────────────
    // Shared columns first; the rate columns are where the two modes differ.
    const storageHead = (l, i) => `
            <td class="ps-2 text-muted">${i + 1}</td>
            <td class="font-monospace fw-semibold">${l.container_no}</td>
            <td class="text-center"><span class="badge bg-dark badge-size">${l.container_size || '—'}'</span></td>
            <td class="small">${fmtEqt(l)}</td>
            <td class="small">${l.cargo_status ? '<span class="badge ' + (l.cargo_status === 'laden' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info') + ' border" style="font-size:.7rem;">' + (l.cargo_status.charAt(0).toUpperCase() + l.cargo_status.slice(1)) + '</span>' : '—'}</td>
            <td class="small">${fmtDate(l.gate_in_date)}</td>
            <td class="text-center small">${fmtDate(l.storage_from)}</td>
            <td class="text-center small">${fmtDate(l.storage_to)}</td>
            <td class="text-center">${l.storage_total_days}d</td>`;

    document.getElementById('storageBody').innerHTML = previewLines.map((l, i) => MANUAL ? `
        <tr id="sRow-${i}">
            ${pickCell('selS', i, 'storage')}
            ${storageHead(l, i)}
            <td class="text-center text-success" id="sFree-${i}">${l.storage_free_days}d</td>
            <td class="text-center" id="sChg-${i}">${l.storage_chargeable_days}d</td>
            <td class="text-end bg-warning-subtle">
                <input type="number" class="rate-input" id="sRate-${i}" min="0" step="0.01"
                       data-line="${i}" data-kind="storage" placeholder="0.00">
            </td>
            <td class="text-end fw-semibold" id="sAmt-${i}">—</td>
            <td class="text-end pe-2 small text-muted" id="sVal-${i}">—</td>
        </tr>
    ` : `
        <tr class="${l.storage_chargeable_days == 0 ? 'text-muted' : ''}">
            ${storageHead(l, i)}
            <td class="text-center text-success">${l.storage_free_days}d</td>
            <td class="text-center ${l.storage_chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success'}">${l.storage_chargeable_days}d</td>
            <td class="text-end bg-warning-subtle small">${fmt(l.storage_daily_rate_usd ?? 0)}</td>
            <td class="text-center bg-warning-subtle small text-muted">${l.storage_tariff_currency || 'USD'}</td>
            <td class="text-end bg-warning-subtle small text-muted">${fmt(l.exchange_rate ?? 1)}</td>
            <td class="text-end bg-warning-subtle fw-semibold small">${fmt(l.storage_daily_rate)}</td>
            <td class="text-end fw-semibold ${l.storage_subtotal == 0 ? 'text-success' : ''}">${fmtAmt(l.storage_subtotal)}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(l.storage_subtotal)}</td>
        </tr>
    `).join('');

    document.getElementById('storageFoot').innerHTML = `
        <tr>
            <td colspan="${MANUAL ? 13 : 15}" class="text-end">Storage Subtotal</td>
            <td class="text-end">${fmtAmt(data.storage_subtotal)}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(data.storage_subtotal)}</td>
        </tr>`;

    // ── Handling: Lift Off ─────────────────────────────────────────────────
    // Carry each line's index in previewLines: recalculation addresses cells by
    // line, and a filtered table's own ordering is not that index.
    const withIdx      = pred => previewLines.map((line, idx) => ({ line, idx })).filter(r => pred(r.line));
    const liftOffLines = withIdx(l => l.has_lift_off);
    const liftOnLines  = withIdx(l => l.has_lift_on);
    document.getElementById('handlingCount').textContent =
        `${liftOffLines.length} lift-off · ${liftOnLines.length} lift-on`;

    const handlingTableTpl = (rowsHtml, cols, count) => count === 0
        ? '<div class="px-3 py-2 text-muted small fst-italic">No events during this period.</div>'
        : `<div class="table-responsive"><table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr>${cols}</tr></thead>
            <tbody>${rowsHtml}</tbody>
           </table></div>`;

    // One builder for both directions — they differ only in which date they show
    // and which colour the rate columns carry.
    const liftCols = (tone, dateLabel, kind) => MANUAL ? `
        <th class="ps-2 text-center" style="width:2.2rem;">
            <input type="checkbox" class="form-check-input select-all" data-table="${kind}" checked title="Select / clear all">
        </th>
        <th>#</th><th>Container</th><th class="text-center">Size</th>
        <th>Equipment</th><th>Status</th><th>${dateLabel}</th>
        <th class="text-end bg-${tone}-subtle" style="font-size:.7rem;">Rate</th>
        <th class="text-end">Amount</th>
        <th class="text-end pe-2 text-muted" style="font-size:.7rem;white-space:nowrap;">Value (LKR)</th>` : `
        <th class="ps-2">#</th><th>Container</th><th class="text-center">Size</th>
        <th>Equipment</th><th>Status</th><th>${dateLabel}</th>
        <th class="text-end bg-${tone}-subtle" style="font-size:.7rem;">Tariff Rate</th>
        <th class="text-center bg-${tone}-subtle" style="font-size:.7rem;">Cur</th>
        <th class="text-end bg-${tone}-subtle" style="font-size:.7rem;">× Exch. Rate</th>
        <th class="text-end">Amount</th>
        <th class="text-end pe-2 text-muted" style="font-size:.7rem;white-space:nowrap;">Value (LKR)</th>`;

    // `idx` is the line's index in previewLines, not its position in this table —
    // recalculation addresses cells by line, and the two orderings are different.
    const liftRows = (rows, kind, tone, dateOf) => rows.map((r, n) => {
        const l = r.line, idx = r.idx;
        const p = kind === 'lift_off' ? 'lo' : 'ln';
        const rateCells = MANUAL ? `
            <td class="text-end bg-${tone}-subtle">
                <input type="number" class="rate-input" id="${p}Rate-${idx}" min="0" step="0.01"
                       data-line="${idx}" data-kind="${kind}" placeholder="0.00">
            </td>
            <td class="text-end fw-semibold" id="${p}Amt-${idx}">—</td>
            <td class="text-end pe-2 small text-muted" id="${p}Val-${idx}">—</td>` : `
            <td class="text-end bg-${tone}-subtle small">${fmt(l[kind + '_rate_usd'] ?? 0)}</td>
            <td class="text-center bg-${tone}-subtle small text-muted">${l.handling_tariff_currency || 'USD'}</td>
            <td class="text-end bg-${tone}-subtle small text-muted">${fmt(l.exchange_rate ?? 1)}</td>
            <td class="text-end fw-semibold">${fmtAmt(l[kind + '_rate'])}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(l[kind + '_rate'])}</td>`;

        return `
        <tr id="${p}Row-${idx}">
            ${MANUAL ? pickCell(p === 'lo' ? 'selLo' : 'selLn', idx, kind) : ''}
            <td class="ps-2 text-muted">${n + 1}</td>
            <td class="font-monospace fw-semibold">${l.container_no}</td>
            <td class="text-center"><span class="badge bg-dark badge-size">${l.container_size || '—'}'</span></td>
            <td class="small">${fmtEqt(l)}</td>
            <td class="small">${l.cargo_status ? '<span class="badge ' + (l.cargo_status === 'laden' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info') + ' border" style="font-size:.7rem;">' + (l.cargo_status.charAt(0).toUpperCase() + l.cargo_status.slice(1)) + '</span>' : '—'}</td>
            <td class="small">${dateOf(l)}</td>
            ${rateCells}
        </tr>`;
    }).join('');

    const liftSubtotal = (id, rows, kind) =>
        `<div class="d-flex justify-content-end px-3 py-1 bg-light border-top small fw-semibold text-muted">
            Lift ${kind === 'lift_off' ? 'Off' : 'On'} Subtotal:
            <span class="ms-2 text-dark" id="${id}">${fmtAmt(rows.reduce((s, r) => s + (parseFloat(r.line[kind + '_rate']) || 0), 0))}</span>
         </div>`;

    document.getElementById('liftOffSection').innerHTML =
        handlingTableTpl(liftRows(liftOffLines, 'lift_off', 'success', l => fmtDate(l.gate_in_date)),
                         liftCols('success', 'Gate In Date', 'lift_off'), liftOffLines.length)
        + (liftOffLines.length ? liftSubtotal('liftOffSubtotal', liftOffLines, 'lift_off') : '');

    document.getElementById('liftOnSection').innerHTML =
        handlingTableTpl(liftRows(liftOnLines, 'lift_on', 'primary', l => l.gate_out_date ? fmtDate(l.gate_out_date) : '—'),
                         liftCols('primary', 'Gate Out Date', 'lift_on'), liftOnLines.length)
        + (liftOnLines.length ? liftSubtotal('liftOnSubtotal', liftOnLines, 'lift_on') : '');

    document.getElementById('handlingSubtotalFooter').textContent = fmtAmt(data.handling_subtotal);

    // ── Invoice Total table ────────────────────────────────────────────────
    renderTotalTable(data, fmtAmt, fmtVal, invCur);

    if (MANUAL) {
        // Everything the period returned is on the bill until the operator says
        // otherwise. A fresh preview starts from that default: the containers are
        // newly loaded, so a stale selection would be about different lines.
        previewLines.forEach(l => { l._selected = true; });

        rateMatrix = data.rate_matrix || [];
        renderMatrix();
        wireLineRateInputs();
        // Draws every derived cell from the rates typed so far — none, on a fresh
        // preview — and blocks the save until they are filled in.
        recalcAll();
    }
}

/**
 * Which sections the current bill type shows. Set by renderPreview and read by
 * renderTotalTable, which runs again on every manual recalculation.
 */
let totalSections = { storage: true, handling: true };

function renderTotalTable(data, fmtAmt, fmtVal, invCur) {
    const showStorage  = totalSections.storage;
    const showHandling = totalSections.handling;

    const ssclRow = parseFloat(data.sscl_amount) > 0
        ? `<tr><td class="ps-3 text-muted">SSCL</td><td class="text-end">${fmtAmt(data.sscl_amount)}</td><td class="text-end pe-3 small text-muted">${fmtVal(data.sscl_amount)}</td></tr>` : '';
    const vatRow  = parseFloat(data.vat_amount) > 0
        ? `<tr><td class="ps-3 text-muted">VAT</td><td class="text-end">${fmtAmt(data.vat_amount)}</td><td class="text-end pe-3 small text-muted">${fmtVal(data.vat_amount)}</td></tr>` : '';
    const storageRow = showStorage
        ? `<tr><td class="ps-3 text-muted"><i class="bi bi-building text-warning me-1"></i>Storage Subtotal</td><td class="text-end fw-semibold">${fmtAmt(data.storage_subtotal)}</td><td class="text-end pe-3 small text-muted">${fmtVal(data.storage_subtotal)}</td></tr>` : '';
    const handlingRow = showHandling
        ? `<tr><td class="ps-3 text-muted"><i class="bi bi-truck text-info me-1"></i>Handling Subtotal</td><td class="text-end fw-semibold">${fmtAmt(data.handling_subtotal)}</td><td class="text-end pe-3 small text-muted">${fmtVal(data.handling_subtotal)}</td></tr>` : '';
    // Combined line only adds value when both sections are present.
    const combinedRow = (showStorage && showHandling)
        ? `<tr class="table-light"><td class="ps-3 fw-semibold">Combined Subtotal</td><td class="text-end fw-semibold">${fmtAmt(data.subtotal)}</td><td class="text-end pe-3 small text-muted">${fmtVal(data.subtotal)}</td></tr>` : '';

    document.getElementById('totalTable').innerHTML = `
        <tbody>
            ${storageRow}${handlingRow}${combinedRow}
            ${ssclRow}${vatRow}
            <tr class="table-success fw-bold">
                <td class="ps-3 fs-6">GRAND TOTAL (${invCur})</td>
                <td class="text-end fs-5">${fmtAmt(data.total_amount)}</td>
                <td class="text-end pe-3 small">${fmtVal(data.total_value ?? data.total_amount)}</td>
            </tr>
        </tbody>`;
}

/**
 * Per-line rate boxes. A value typed here overrides the matrix for that line
 * only; clearing it hands the line back to the matrix rather than blanking it.
 */
function wireLineRateInputs() {
    document.querySelectorAll('input.rate-input[data-line]').forEach(inp => {
        inp.addEventListener('input', function () {
            const line = previewLines[parseInt(this.dataset.line, 10)];
            if (!line) return;
            line['_ovr_' + this.dataset.kind] = this.value === '' ? null : this.value;
            recalcAll();
        });
    });

    document.querySelectorAll('input.line-pick').forEach(box => {
        box.addEventListener('change', function () {
            const line = previewLines[parseInt(this.dataset.line, 10)];
            if (!line) return;
            line._selected = this.checked;
            recalcAll();
        });
    });

    document.querySelectorAll('input.select-all').forEach(box => {
        box.addEventListener('change', function () {
            setTableSelection(this.dataset.table, this.checked);
        });
    });
}

function fmtDate(d) {
    if (!d) return '—';
    const [y, m, dd] = d.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${dd} ${months[parseInt(m)-1]} ${y}`;
}

function showAlert(type, msg) {
    const a = document.getElementById('tariffAlert');
    a.className = `alert alert-${type} d-flex align-items-center gap-2 mb-3`;
    a.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i> ${msg}`;
    a.classList.remove('d-none');
}

// Wire up all event handlers once DOM + jQuery plugins are ready
$(document).ready(function () {
    document.getElementById('previewBtn').addEventListener('click', runPreview);

    // 'changeDate' fires when Bootstrap Datepicker picks a date;
    // 'change'/'input' covers manual/typed entry
    $('#invoiceDate').on('change input changeDate', onDateChange);

    $('#shippingLineId').on('change select2:select', onShippingLineChange);
    $('#billingPartyId').on('change select2:select', onBillingPartyChange);

    // Changing the bill type invalidates the current preview — force a fresh one.
    document.querySelectorAll('input[name="bill_type"]').forEach(el =>
        el.addEventListener('change', () => {
            previewLines = [];
            document.getElementById('summarySection').classList.add('d-none');
            document.getElementById('previewPlaceholder').classList.remove('d-none');
        })
    );

    // Free time changes every line's free/chargeable split — each against its own
    // remaining balance, not a flat number — so it recalculates in place rather
    // than needing another preview.
    const freeDaysEl = document.getElementById('manualFreeDays');
    if (freeDaysEl) {
        freeDaysEl.addEventListener('input', () => {
            if (previewLines.length) recalcAll();
        });
    }

    // Inject hidden inputs from preview before save
    document.getElementById('billingForm').addEventListener('submit', function (e) {
        if (previewLines.length === 0) {
            e.preventDefault();
            showToast('Please run a preview first.', 'warning');
            return;
        }

        if (!MANUAL && previewMissing.length > 0) {
            e.preventDefault();
            showToast('Cannot save — missing tariff rates. Update the tariff and preview again.', 'danger');
            return;
        }

        if (MANUAL && nothingSelected()) {
            e.preventDefault();
            showToast('Cannot save — tick at least one container to bill.', 'danger');
            return;
        }

        if (MANUAL && manualBlockers().length > 0) {
            e.preventDefault();
            showToast('Cannot save — every chargeable line needs a rate.', 'danger');
            return;
        }

        this.querySelectorAll('[name^="lines["], [name="invoice_currency"], [name="exchange_rate"]')
            .forEach(el => el.remove());

        const invoiceCurrency = document.getElementById('invoiceCurrency').value;
        const exchangeRate    = parseFloat(document.getElementById('exchangeRate').value || 1);

        const mkHidden = (name, val) => {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = name; inp.value = val ?? '';
            this.appendChild(inp);
        };
        mkHidden('invoice_currency', invoiceCurrency);
        mkHidden('exchange_rate', exchangeRate);

        // Only the containers on the bill, re-indexed so lines[0..n] is
        // contiguous. An unticked container simply has no line — which is why
        // nothing downstream needs to know the selection existed.
        //
        // `_`-prefixed keys are screen state (which lines were overridden, which
        // are on the bill) and have no business in the request.
        const toPost = MANUAL ? previewLines.filter(selected) : previewLines;

        toPost.forEach((line, i) => {
            Object.entries(line).forEach(([key, val]) => {
                if (key.startsWith('_')) return;
                mkHidden(`lines[${i}][${key}]`, val);
            });
        });
    });
});
</script>
@endpush
