@extends('layouts.app')

@section('title', 'Generate Storage Invoice')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.index') }}">Billing</a></li>
    <li class="breadcrumb-item active">Generate Invoice</li>
@endsection

@push('styles')
<style>
    .summary-card {
        border-radius: 10px;
        background: linear-gradient(135deg, #1a56db 0%, #1035a0 100%);
        color: #fff;
    }
    .summary-card .label { opacity: .75; font-size: .78rem; }
    #previewTable th, #previewTable td { font-size: .82rem; padding: .4rem .65rem; }
    .no-tariff-badge { font-size: .72rem; }
    #billingPartyBox { border-left: 3px solid #0d6efd; }
</style>
@endpush

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Generate Storage Invoice</h4>
        <p class="text-muted mb-0 small">Select a customer and billing period to calculate storage charges</p>
    </div>
    <a href="{{ route('billing.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<form id="billingForm" method="POST" action="{{ route('billing.store') }}">
@csrf

<div class="row g-3">

    <!-- ── Left: Parameters ─────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-sliders me-2 text-primary"></i>Invoice Parameters
            </div>
            <div class="card-body">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Customer / Operator <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerId" class="form-select select2 s2-code" required data-s2-sel="name">
                        <option value="">— Select Customer —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}"
                                    data-code="{{ $c->code }}" data-name="{{ $c->name }}"
                                    data-tax-exempt="{{ $c->tax_exempt ? '1' : '0' }}"
                                    data-billing-party-id="{{ $c->billing_party_id ?? '' }}"
                                    data-billing-party-name="{{ $c->billingParty->name ?? '' }}"
                                    data-billing-party-address="{{ $c->billingParty->address ?? '' }}">
                                [{{ $c->code }}] {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Billing Party (searchable dropdown, auto-set from Customer master, overridable) -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Billing Party</label>
                    <select name="billing_party_id" id="billingPartyId" class="form-select select2 s2-code" data-s2-sel="name">
                        <option value="">— Select Billing Party —</option>
                        @foreach($customers as $c)
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
                    <label class="form-label fw-semibold">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" id="invoiceDate" class="form-control"
                           value="{{ date('Y-m-d') }}" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-5">
                        <label class="form-label fw-semibold">Invoice Currency <span class="text-danger">*</span></label>
                        <select id="invoiceCurrency" class="form-select">
                            <option value="LKR" selected>LKR — Sri Lankan Rupee</option>
                            <option value="USD">USD — US Dollar</option>
                            <option value="EUR">EUR — Euro</option>
                            <option value="GBP">GBP — British Pound</option>
                            <option value="SGD">SGD — Singapore Dollar</option>
                            <option value="AUD">AUD — Australian Dollar</option>
                        </select>
                        <div class="form-text">Values always stored in LKR</div>
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
                        <label class="form-label fw-semibold">Period From <span class="text-danger">*</span></label>
                        <input type="date" name="period_from" id="periodFrom" class="form-control"
                               value="{{ date('Y-m-01') }}" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Period To <span class="text-danger">*</span></label>
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

        <!-- Tariff status -->
        <div id="tariffAlert" class="d-none"></div>

    </div>

    <!-- ── Right: Preview & Save ─────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Summary card (hidden until preview) -->
        <div id="summarySection" class="d-none">
            <div class="summary-card p-4 mb-3">
                <div class="row g-2 text-center">
                    <div class="col-3">
                        <div class="label">Containers</div>
                        <div class="fs-3 fw-bold" id="sumContainers">0</div>
                    </div>
                    <div class="col-3">
                        <div class="label">Subtotal</div>
                        <div class="fs-4 fw-bold" id="sumSubtotal">0.00</div>
                    </div>
                    <div class="col-3">
                        <div class="label">SSCL</div>
                        <div class="fs-4 fw-bold" id="sumSscl">0.00</div>
                    </div>
                    <div class="col-3">
                        <div class="label">VAT</div>
                        <div class="fs-4 fw-bold" id="sumVat">0.00</div>
                    </div>
                    <div class="col-12 border-top border-white border-opacity-25 pt-2">
                        <div class="label">Total Invoice Amount</div>
                        <div class="display-5 fw-bold" id="sumTotal">0.00</div>
                    </div>
                </div>
            </div>

            <!-- Container charge lines table -->
            <div class="card content-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-table me-2 text-primary"></i>Container Charge Lines</span>
                    <span id="lineCount" class="badge bg-secondary-subtle text-secondary"></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="previewTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">#</th>
                                    <th>Container No.</th>
                                    <th>Equipment</th>
                                    <th class="text-center">Status</th>
                                    <th>Gate-In</th>
                                    <th class="text-center">Period</th>
                                    <th class="text-center">Days</th>
                                    <th class="text-center">Free</th>
                                    <th class="text-center">Chgbl</th>
                                    <th class="text-end">Rate/Day</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">Tax1%</th>
                                    <th class="text-end">SSCL</th>
                                    <th class="text-end">Tax2%</th>
                                    <th class="text-end">VAT</th>
                                    <th class="text-end" id="amountHeader">Amount</th>
                                    <th class="text-end pe-3 text-muted" style="font-size:.75rem;">Value (LKR)</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                            <tfoot id="previewFoot" class="table-light fw-semibold"></tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Save button -->
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('billing.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x me-1"></i>Cancel
                </a>
                <button type="submit" id="saveBtn" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>Save Invoice
                </button>
            </div>
        </div>

        <!-- Placeholder before preview -->
        <div id="previewPlaceholder" class="card content-card">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-receipt fs-1 d-block mb-3 text-primary opacity-25"></i>
                <p class="mb-1">Select a customer and billing period,<br>then click <strong>Preview Charges</strong>.</p>
                <p class="small">All containers currently in yard for the selected operator will be listed with their storage charges for the period.</p>
            </div>
        </div>

    </div>
</div>

<!-- Hidden line inputs will be injected here by JS before submit -->

</form>

@endsection

@push('scripts')
<script>
const csrfToken      = '{{ csrf_token() }}';
const previewUrl     = '{{ route("billing.preview") }}';
const exchRateUrl = '/billing/exchange-rate';

let previewLines = [];

function fmtEqt(l) {
    if (!l.eqt_code) return l.equipment_type || '—';
    const isReefer = l.type_code && ['RF','RH'].includes(l.type_code);
    const chip = isReefer
        ? '<span class="badge badge-reefer" style="font-size:.72rem;">' + l.eqt_code + '</span>'
        : '<span class="fw-semibold">' + l.eqt_code + '</span>';
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

// Re-fetch rate when invoice date changes (both change and input for date-picker compat)
function onDateChange() {
    const val = document.getElementById('invoiceDate').value;
    if (val && /^\d{4}-\d{2}-\d{2}$/.test(val)) fetchExchangeRate();
}
// Customer selection: auto-set invoice type, billing party, tax-exempt alert
function onCustomerChange() {
    const val    = $('#customerId').val();
    const $opt   = $('#customerId').find('option[value="' + val + '"]');
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
async function runPreview() {
    const customerId      = document.getElementById('customerId').value;
    const periodFrom      = document.getElementById('periodFrom').value;
    const periodTo        = document.getElementById('periodTo').value;
    const invoiceCurrency = document.getElementById('invoiceCurrency').value;
    const exchangeRate    = parseFloat(document.getElementById('exchangeRate').value || 1);

    if (!customerId) { showToast('Please select a customer.', 'warning'); return; }
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
                customer_id:      customerId,
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

    // Tax exempt alert
    document.getElementById('taxExemptAlert').classList.toggle('d-none', !data.tax_exempt);

    // Tariff alert
    const alertBox = document.getElementById('tariffAlert');
    if (!data.tariff_found) {
        alertBox.className = 'alert alert-warning d-flex align-items-start gap-2 mb-3';
        alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill mt-1"></i><div><strong>No active storage tariff found</strong> for this customer. Rates shown are from the stored gate-in values and may be outdated. <a href="{{ route("masters.storage-tariff.index") }}">Set up a tariff &rarr;</a></div>';
    } else {
        alertBox.className = 'alert alert-success d-flex align-items-center gap-2 mb-3';
        alertBox.innerHTML = '<i class="bi bi-check-circle-fill"></i> Rates loaded from active storage tariff.';
    }
    alertBox.classList.remove('d-none');

    if (data.no_containers || previewLines.length === 0) {
        alertBox.className = 'alert alert-info d-flex align-items-center gap-2 mb-3';
        alertBox.innerHTML = '<i class="bi bi-info-circle-fill"></i> No containers currently in yard for this customer during the selected period.';
        document.getElementById('summarySection').classList.add('d-none');
        document.getElementById('previewPlaceholder').classList.remove('d-none');
        return;
    }

    document.getElementById('previewPlaceholder').classList.add('d-none');
    document.getElementById('summarySection').classList.remove('d-none');

    const fmt    = n => parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fmtC   = (n, cur) => (cur || 'LKR') + '\xa0' + fmt(n);
    const invCur = data.invoice_currency || 'LKR';
    const defCur = data.default_currency || 'LKR';
    const exRate = parseFloat(data.exchange_rate) || 1;

    // Amount = invoice-currency display; Value = default-currency (LKR) stored amount
    const toAmt  = lkr => invCur === defCur ? parseFloat(lkr) : parseFloat(lkr) / exRate;
    const fmtAmt = lkr => fmtC(toAmt(lkr), invCur);
    const fmtVal = lkr => fmtC(lkr, defCur);

    // Update Amount column header to show invoice currency
    document.getElementById('amountHeader').textContent = 'Amount (' + invCur + ')';

    // Summary card — show invoice-currency amounts
    document.getElementById('sumContainers').textContent = previewLines.length;
    document.getElementById('sumSubtotal').textContent   = fmtAmt(data.subtotal);
    document.getElementById('sumSscl').textContent       = fmtAmt(data.sscl_amount);
    document.getElementById('sumVat').textContent        = fmtAmt(data.vat_amount);
    document.getElementById('sumTotal').textContent      = fmtAmt(data.total_display ?? data.total_amount);
    document.getElementById('lineCount').textContent     = previewLines.length + ' containers';

    // Lines table
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = previewLines.map((l, i) => `
        <tr class="${l.chargeable_days === 0 ? 'text-muted' : ''}">
            <td class="ps-3">${i + 1}</td>
            <td class="font-monospace">${l.container_no}</td>
            <td class="small">${fmtEqt(l)}</td>
            <td class="text-center">${l.cargo_status === 'laden' ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:.7rem;">Laden</span>' : '<span class="badge bg-info-subtle text-info border border-info-subtle" style="font-size:.7rem;">Empty</span>'}</td>
            <td class="small">${formatDate(l.gate_in_date)}</td>
            <td class="text-center small">${formatDate(l.from_date)} – ${formatDate(l.to_date)}</td>
            <td class="text-center"><span class="badge bg-light border text-dark">${l.total_days}d</span></td>
            <td class="text-center text-success small">${l.free_days}d</td>
            <td class="text-center ${l.chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success'}">${l.chargeable_days}d</td>
            <td class="text-end small">${fmtAmt(l.daily_rate)}</td>
            <td class="text-end fw-semibold ${l.subtotal == 0 ? 'text-success' : ''}">${fmtAmt(l.subtotal)}</td>
            <td class="text-end small text-muted">${parseFloat(l.tax1_rate||0).toFixed(2)}%</td>
            <td class="text-end small text-secondary">${fmtAmt(l.line_sscl)}</td>
            <td class="text-end small text-muted">${parseFloat(l.tax2_rate||0).toFixed(2)}%</td>
            <td class="text-end small text-secondary">${fmtAmt(l.line_vat)}</td>
            <td class="text-end fw-bold">${fmtAmt(l.line_amount ?? l.line_total)}</td>
            <td class="text-end pe-3 text-muted small">${fmtVal(l.line_value ?? l.line_total)}</td>
        </tr>
    `).join('');

    // Footer
    const tfoot = document.getElementById('previewFoot');
    tfoot.innerHTML = `
        <tr>
            <td class="ps-3" colspan="15" style="text-align:right">Subtotal</td>
            <td class="text-end">${fmtAmt(data.subtotal)}</td>
            <td class="text-end pe-3 text-muted small">${fmtVal(data.subtotal)}</td>
        </tr>
        <tr class="text-muted" style="font-weight:400">
            <td class="ps-3" colspan="15" style="text-align:right">SSCL</td>
            <td class="text-end">${fmtAmt(data.sscl_amount)}</td>
            <td class="text-end pe-3 small">${fmtVal(data.sscl_amount)}</td>
        </tr>
        <tr class="text-muted" style="font-weight:400">
            <td class="ps-3" colspan="15" style="text-align:right">VAT</td>
            <td class="text-end">${fmtAmt(data.vat_amount)}</td>
            <td class="text-end pe-3 small">${fmtVal(data.vat_amount)}</td>
        </tr>
        <tr class="table-primary">
            <td class="ps-3" colspan="15" style="text-align:right">TOTAL</td>
            <td class="text-end">${fmtAmt(data.total_display ?? data.total_amount)}</td>
            <td class="text-end pe-3 small">${fmtVal(data.total_value ?? data.total_amount)}</td>
        </tr>
    `;
}

function formatDate(d) {
    if (!d) return '—';
    const [y, m, dd] = d.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${dd} ${months[parseInt(m)-1]} ${y}`;
}

function showAlert(type, msg) {
    const alertBox = document.getElementById('tariffAlert');
    alertBox.className = `alert alert-${type} d-flex align-items-center gap-2 mb-3`;
    alertBox.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i> ${msg}`;
    alertBox.classList.remove('d-none');
}

// Wire up all event handlers once DOM + jQuery plugins are ready
$(document).ready(function () {
    document.getElementById('previewBtn').addEventListener('click', runPreview);

    // 'changeDate' fires when Bootstrap Datepicker picks a date;
    // 'change'/'input' covers manual/typed entry
    $('#invoiceDate').on('change input changeDate', onDateChange);

    $('#customerId').on('change select2:select', onCustomerChange);
    $('#billingPartyId').on('change select2:select', onBillingPartyChange);

    // Inject hidden form inputs from preview lines before save
    document.getElementById('billingForm').addEventListener('submit', function (e) {
        if (previewLines.length === 0) {
            e.preventDefault();
            showToast('Please run a preview first.', 'warning');
            return;
        }

        // Remove any stale inputs
        this.querySelectorAll('[name^="lines["], [name="invoice_currency"], [name="exchange_rate"]')
            .forEach(el => el.remove());

        const invoiceCurrency = document.getElementById('invoiceCurrency').value;
        const exchangeRate    = parseFloat(document.getElementById('exchangeRate').value || 1);

        const mkHidden = (name, val) => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = name; i.value = val;
            this.appendChild(i);
        };
        mkHidden('invoice_currency', invoiceCurrency);
        mkHidden('exchange_rate', exchangeRate);

        // Add hidden inputs for each line
        previewLines.forEach((line, i) => {
            Object.entries(line).forEach(([key, val]) => {
                if (key === 'tariff_found') return;
                mkHidden(`lines[${i}][${key}]`, val);
            });
        });
    });
});
</script>
@endpush
