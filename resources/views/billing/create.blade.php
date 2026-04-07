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
                    <select name="customer_id" id="customerId" class="form-select" required>
                        <option value="">— Select Customer —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}"
                                    data-email="{{ $c->email }}"
                                    data-currency="{{ $c->currency }}">
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control"
                           value="{{ date('Y-m-d') }}" required>
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

                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label fw-semibold mb-0">SSCL</label>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="applySscl" checked>
                        </div>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="number" id="ssclPct" class="form-control"
                               value="2.5" min="0" max="100" step="0.01">
                        <span class="input-group-text">%</span>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <label class="form-label fw-semibold mb-0">VAT</label>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="applyVat" checked>
                        </div>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="number" id="vatPct" class="form-control"
                               value="18" min="0" max="100" step="0.01">
                        <span class="input-group-text">%</span>
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
                                    <th>Gate-In</th>
                                    <th class="text-center">Period</th>
                                    <th class="text-center">Days</th>
                                    <th class="text-center">Free</th>
                                    <th class="text-center">Chargeable</th>
                                    <th class="text-end">Rate/Day</th>
                                    <th class="text-end">Subtotal</th>
                                    <th class="text-end">SSCL</th>
                                    <th class="text-end">VAT</th>
                                    <th class="text-end pe-3">Line Total</th>
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
const csrfToken = '{{ csrf_token() }}';
const previewUrl = '{{ route("billing.preview") }}';

let previewLines = [];

document.getElementById('previewBtn').addEventListener('click', runPreview);

// Toggle SSCL/VAT input availability
document.getElementById('applySscl').addEventListener('change', function () {
    document.getElementById('ssclPct').disabled = !this.checked;
});
document.getElementById('applyVat').addEventListener('change', function () {
    document.getElementById('vatPct').disabled = !this.checked;
});

async function runPreview() {
    const customerId = document.getElementById('customerId').value;
    const periodFrom = document.getElementById('periodFrom').value;
    const periodTo   = document.getElementById('periodTo').value;
    const ssclPct    = document.getElementById('applySscl').checked
                       ? parseFloat(document.getElementById('ssclPct').value || 0) : 0;
    const vatPct     = document.getElementById('applyVat').checked
                       ? parseFloat(document.getElementById('vatPct').value || 0) : 0;

    if (!customerId) { alert('Please select a customer.'); return; }
    if (!periodFrom || !periodTo) { alert('Please enter the billing period dates.'); return; }

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
                customer_id: customerId,
                period_from: periodFrom,
                period_to: periodTo,
                sscl_pct: ssclPct,
                vat_pct: vatPct,
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

    const fmt  = n => parseFloat(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fmtC = (n, cur) => (cur || 'LKR') + '\u00a0' + fmt(n);

    // Summary card
    const currency = previewLines[0]?.currency || 'LKR';
    document.getElementById('sumContainers').textContent = previewLines.length;
    document.getElementById('sumSubtotal').textContent   = fmtC(data.subtotal, currency);
    document.getElementById('sumSscl').textContent       = fmtC(data.sscl_amount, currency);
    document.getElementById('sumVat').textContent        = fmtC(data.vat_amount, currency);
    document.getElementById('sumTotal').textContent      = fmtC(data.total_amount, currency);
    document.getElementById('lineCount').textContent     = previewLines.length + ' containers';

    // Lines table
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = previewLines.map((l, i) => `
        <tr class="${l.chargeable_days === 0 ? 'text-muted' : ''}">
            <td class="ps-3">${i + 1}</td>
            <td class="font-monospace">${l.container_no}</td>
            <td class="small">${l.equipment_type}</td>
            <td class="small">${formatDate(l.gate_in_date)}</td>
            <td class="text-center small">${formatDate(l.from_date)} – ${formatDate(l.to_date)}</td>
            <td class="text-center"><span class="badge bg-light border text-dark">${l.total_days}d</span></td>
            <td class="text-center text-success small">${l.free_days}d</td>
            <td class="text-center ${l.chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success'}">${l.chargeable_days}d</td>
            <td class="text-end small">${fmtC(l.daily_rate, l.currency)}</td>
            <td class="text-end fw-semibold ${l.subtotal == 0 ? 'text-success' : ''}">${fmtC(l.subtotal, l.currency)}</td>
            <td class="text-end small text-secondary">${fmtC(l.line_sscl, l.currency)}</td>
            <td class="text-end small text-secondary">${fmtC(l.line_vat, l.currency)}</td>
            <td class="text-end pe-3 fw-bold">${fmtC(l.line_total, l.currency)}</td>
        </tr>
    `).join('');

    // Footer
    const ssclLabel = data.sscl_percentage > 0 ? `SSCL (${parseFloat(data.sscl_percentage).toFixed(2)}%)` : 'SSCL';
    const vatLabel  = data.vat_percentage  > 0 ? `VAT (${parseFloat(data.vat_percentage).toFixed(2)}%)`   : 'VAT';
    const tfoot = document.getElementById('previewFoot');
    tfoot.innerHTML = `
        <tr>
            <td class="ps-3" colspan="12" style="text-align:right">Subtotal</td>
            <td class="text-end pe-3">${fmtC(data.subtotal, currency)}</td>
        </tr>
        <tr class="text-muted" style="font-weight:400">
            <td class="ps-3" colspan="12" style="text-align:right">${ssclLabel}</td>
            <td class="text-end pe-3">${fmtC(data.sscl_amount, currency)}</td>
        </tr>
        <tr class="text-muted" style="font-weight:400">
            <td class="ps-3" colspan="12" style="text-align:right">${vatLabel}</td>
            <td class="text-end pe-3">${fmtC(data.vat_amount, currency)}</td>
        </tr>
        <tr class="table-primary">
            <td class="ps-3" colspan="12" style="text-align:right">TOTAL</td>
            <td class="text-end pe-3">${fmtC(data.total_amount, currency)}</td>
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

// Inject hidden form inputs from preview lines before save
document.getElementById('billingForm').addEventListener('submit', function (e) {
    if (previewLines.length === 0) {
        e.preventDefault();
        alert('Please run a preview first.');
        return;
    }

    // Remove any stale inputs
    this.querySelectorAll('[name^="lines["], [name="sscl_percentage"], [name="vat_percentage"]')
        .forEach(el => el.remove());

    // Inject tax percentages
    const ssclPct = document.getElementById('applySscl').checked
                    ? parseFloat(document.getElementById('ssclPct').value || 0) : 0;
    const vatPct  = document.getElementById('applyVat').checked
                    ? parseFloat(document.getElementById('vatPct').value || 0) : 0;

    const mkHidden = (name, val) => {
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = name; i.value = val;
        this.appendChild(i);
    };
    mkHidden('sscl_percentage', ssclPct);
    mkHidden('vat_percentage', vatPct);

    // Add hidden inputs for each line
    previewLines.forEach((line, i) => {
        Object.entries(line).forEach(([key, val]) => {
            // Skip frontend-only keys
            if (key === 'tariff_found') return;
            mkHidden(`lines[${i}][${key}]`, val);
        });
    });
});
</script>
@endpush
