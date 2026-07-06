@extends('layouts.app')

@section('title', 'Generate Storage & Handling Invoice')

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('billing.storage-handling.index') }}" class="text-decoration-none">Storage &amp; Handling</a>
    </li>
    <li class="breadcrumb-item active">Generate Invoice</li>
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
</style>
@endpush

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Generate Storage &amp; Handling Invoice</h4>
        <p class="text-muted mb-0 small">
            Calculates storage charges plus Lift Off (Gate In) and Lift On (Gate Out) handling for the selected period
        </p>
    </div>
    <a href="{{ route('billing.storage-handling.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<form id="billingForm" method="POST" action="{{ route('billing.storage-handling.store') }}">
@csrf

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
const previewUrl  = '{{ route("billing.storage-handling.preview") }}';
const exchRateUrl = @json(route('finance.fx-rate'));

let previewLines = [];
let previewMissing = [];

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

function renderPreview(data) {
    previewLines = data.lines || [];
    previewMissing = data.missing_rates || [];

    // Show only the sections relevant to the chosen bill type.
    const billType     = data.bill_type || 'storage_handling';
    const showStorage  = billType !== 'handling_only';
    const showHandling = billType !== 'storage_only';
    document.getElementById('storageTile').classList.toggle('d-none', !showStorage);
    document.getElementById('handlingTile').classList.toggle('d-none', !showHandling);
    document.getElementById('storageCard').classList.toggle('d-none', !showStorage);
    document.getElementById('handlingCard').classList.toggle('d-none', !showHandling);

    // Missing tariff rates → render the detail panel and block saving
    const hasMissing = window.renderTariffMissing(document.getElementById('missingRatesPanel'), previewMissing);
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.disabled = hasMissing;
        saveBtn.title = hasMissing ? 'Resolve the missing tariff rates before saving' : '';
    }

    // Tax exempt alert
    document.getElementById('taxExemptAlert').classList.toggle('d-none', !data.tax_exempt);

    // Tariff status alerts — only warn about a tariff for a section that is
    // actually being billed. On a storage-only or handling-only bill the other
    // section's tariff is never looked up (so its *_tariff_found flag is false by
    // design); warning about it would be misleading.
    const alertBox = document.getElementById('tariffAlert');
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
        alertBox.classList.remove('d-none');
    } else {
        const loaded = [];
        if (showStorage)  loaded.push('storage');
        if (showHandling) loaded.push('handling');
        const label = loaded.length === 2
            ? 'Both storage and handling tariffs'
            : (loaded[0].charAt(0).toUpperCase() + loaded[0].slice(1) + ' tariff');
        alertBox.className = 'alert alert-success d-flex align-items-center gap-2 mb-3';
        alertBox.innerHTML = '<i class="bi bi-check-circle-fill"></i> ' + label + ' loaded successfully.';
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
    document.getElementById('storageBody').innerHTML = previewLines.map((l, i) => `
        <tr class="${l.storage_chargeable_days == 0 ? 'text-muted' : ''}">
            <td class="ps-2 text-muted">${i + 1}</td>
            <td class="font-monospace fw-semibold">${l.container_no}</td>
            <td class="text-center"><span class="badge bg-dark badge-size">${l.container_size || '—'}'</span></td>
            <td class="small">${fmtEqt(l)}</td>
            <td class="small">${l.cargo_status ? '<span class="badge ' + (l.cargo_status === 'laden' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info') + ' border" style="font-size:.7rem;">' + (l.cargo_status.charAt(0).toUpperCase() + l.cargo_status.slice(1)) + '</span>' : '—'}</td>
            <td class="small">${fmtDate(l.gate_in_date)}</td>
            <td class="text-center small">${fmtDate(l.storage_from)}</td>
            <td class="text-center small">${fmtDate(l.storage_to)}</td>
            <td class="text-center">${l.storage_total_days}d</td>
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
            <td colspan="15" class="text-end">Storage Subtotal</td>
            <td class="text-end">${fmtAmt(data.storage_subtotal)}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(data.storage_subtotal)}</td>
        </tr>`;

    // ── Handling: Lift Off ─────────────────────────────────────────────────
    const liftOffLines = previewLines.filter(l => l.has_lift_off);
    const liftOnLines  = previewLines.filter(l => l.has_lift_on);
    document.getElementById('handlingCount').textContent =
        `${liftOffLines.length} lift-off · ${liftOnLines.length} lift-on`;

    const handlingTableTpl = (rowsHtml, cols, count) => count === 0
        ? '<div class="px-3 py-2 text-muted small fst-italic">No events during this period.</div>'
        : `<div class="table-responsive"><table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr>${cols}</tr></thead>
            <tbody>${rowsHtml}</tbody>
           </table></div>`;

    const liftOffCols = `
        <th class="ps-2">#</th><th>Container</th><th class="text-center">Size</th>
        <th>Equipment</th><th>Status</th><th>Gate In Date</th>
        <th class="text-end bg-success-subtle" style="font-size:.7rem;">Tariff Rate</th>
        <th class="text-center bg-success-subtle" style="font-size:.7rem;">Cur</th>
        <th class="text-end bg-success-subtle" style="font-size:.7rem;">× Exch. Rate</th>
        <th class="text-end">Amount</th>
        <th class="text-end pe-2 text-muted" style="font-size:.7rem;white-space:nowrap;">Value (LKR)</th>`;
    const liftOffRows = liftOffLines.map((l, i) => `
        <tr>
            <td class="ps-2 text-muted">${i + 1}</td>
            <td class="font-monospace fw-semibold">${l.container_no}</td>
            <td class="text-center"><span class="badge bg-dark badge-size">${l.container_size || '—'}'</span></td>
            <td class="small">${fmtEqt(l)}</td>
            <td class="small">${l.cargo_status ? '<span class="badge ' + (l.cargo_status === 'laden' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info') + ' border" style="font-size:.7rem;">' + (l.cargo_status.charAt(0).toUpperCase() + l.cargo_status.slice(1)) + '</span>' : '—'}</td>
            <td class="small">${fmtDate(l.gate_in_date)}</td>
            <td class="text-end bg-success-subtle small">${fmt(l.lift_off_rate_usd ?? 0)}</td>
            <td class="text-center bg-success-subtle small text-muted">${l.handling_tariff_currency || 'USD'}</td>
            <td class="text-end bg-success-subtle small text-muted">${fmt(l.exchange_rate ?? 1)}</td>
            <td class="text-end fw-semibold">${fmtAmt(l.lift_off_rate)}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(l.lift_off_rate)}</td>
        </tr>`).join('');
    document.getElementById('liftOffSection').innerHTML = handlingTableTpl(liftOffRows, liftOffCols, liftOffLines.length)
        + (liftOffLines.length ? `<div class="d-flex justify-content-end px-3 py-1 bg-light border-top small fw-semibold text-muted">
            Lift Off Subtotal: <span class="ms-2 text-dark">${fmtAmt(liftOffLines.reduce((s, l) => s + parseFloat(l.lift_off_rate), 0))}</span></div>` : '');

    const liftOnCols = `
        <th class="ps-2">#</th><th>Container</th><th class="text-center">Size</th>
        <th>Equipment</th><th>Status</th><th>Gate Out Date</th>
        <th class="text-end bg-primary-subtle" style="font-size:.7rem;">Tariff Rate</th>
        <th class="text-center bg-primary-subtle" style="font-size:.7rem;">Cur</th>
        <th class="text-end bg-primary-subtle" style="font-size:.7rem;">× Exch. Rate</th>
        <th class="text-end">Amount</th>
        <th class="text-end pe-2 text-muted" style="font-size:.7rem;white-space:nowrap;">Value (LKR)</th>`;
    const liftOnRows = liftOnLines.map((l, i) => `
        <tr>
            <td class="ps-2 text-muted">${i + 1}</td>
            <td class="font-monospace fw-semibold">${l.container_no}</td>
            <td class="text-center"><span class="badge bg-dark badge-size">${l.container_size || '—'}'</span></td>
            <td class="small">${fmtEqt(l)}</td>
            <td class="small">${l.cargo_status ? '<span class="badge ' + (l.cargo_status === 'laden' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info') + ' border" style="font-size:.7rem;">' + (l.cargo_status.charAt(0).toUpperCase() + l.cargo_status.slice(1)) + '</span>' : '—'}</td>
            <td class="small">${l.gate_out_date ? fmtDate(l.gate_out_date) : '—'}</td>
            <td class="text-end bg-primary-subtle small">${fmt(l.lift_on_rate_usd ?? 0)}</td>
            <td class="text-center bg-primary-subtle small text-muted">${l.handling_tariff_currency || 'USD'}</td>
            <td class="text-end bg-primary-subtle small text-muted">${fmt(l.exchange_rate ?? 1)}</td>
            <td class="text-end fw-semibold">${fmtAmt(l.lift_on_rate)}</td>
            <td class="text-end pe-2 small text-muted">${fmtVal(l.lift_on_rate)}</td>
        </tr>`).join('');
    document.getElementById('liftOnSection').innerHTML = handlingTableTpl(liftOnRows, liftOnCols, liftOnLines.length)
        + (liftOnLines.length ? `<div class="d-flex justify-content-end px-3 py-1 bg-light border-top small fw-semibold text-muted">
            Lift On Subtotal: <span class="ms-2 text-dark">${fmtAmt(liftOnLines.reduce((s, l) => s + parseFloat(l.lift_on_rate), 0))}</span></div>` : '');

    document.getElementById('handlingSubtotalFooter').textContent = fmtAmt(data.handling_subtotal);

    // ── Invoice Total table ────────────────────────────────────────────────
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

    // Inject hidden inputs from preview before save
    document.getElementById('billingForm').addEventListener('submit', function (e) {
        if (previewLines.length === 0) {
            e.preventDefault();
            showToast('Please run a preview first.', 'warning');
            return;
        }

        if (previewMissing.length > 0) {
            e.preventDefault();
            showToast('Cannot save — missing tariff rates. Update the tariff and preview again.', 'danger');
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

        previewLines.forEach((line, i) => {
            Object.entries(line).forEach(([key, val]) => {
                mkHidden(`lines[${i}][${key}]`, val);
            });
        });
    });
});
</script>
@endpush
