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
                    <label class="form-label fw-semibold">
                        Shipping Line <span class="text-danger">*</span>
                    </label>
                    <select name="shipping_line_id" id="shippingLineId" class="form-select" required>
                        <option value="">— Select Shipping Line —</option>
                        @foreach($shippingLines as $sl)
                            <option value="{{ $sl->id }}">
                                [{{ $sl->code }}] {{ $sl->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($shippingLines->isEmpty())
                        <div class="form-text text-warning">
                            No active shipping-line customers found. Add one under Customers first.
                        </div>
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        Invoice Date <span class="text-danger">*</span>
                    </label>
                    <input type="date" name="invoice_date" class="form-control"
                           value="{{ date('Y-m-d') }}" required>
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

        {{-- Tariff alert box --}}
        <div id="tariffAlert" class="d-none"></div>

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
                    <div class="col-3">
                        <div class="label">Storage</div>
                        <div class="fs-5 fw-bold" id="sumStorage">0.00</div>
                    </div>
                    <div class="col-3">
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

            {{-- Lines table --}}
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
                                    <th class="ps-2" rowspan="2" style="vertical-align:middle;">#</th>
                                    <th rowspan="2" style="vertical-align:middle;">Container</th>
                                    <th rowspan="2" class="text-center" style="vertical-align:middle;">Size</th>
                                    <th rowspan="2" style="vertical-align:middle;">Gate In</th>
                                    <th colspan="4" class="text-center bg-warning-subtle" style="border-bottom:1px solid #dee2e6;">
                                        Storage
                                    </th>
                                    <th colspan="3" class="text-center bg-info-subtle" style="border-bottom:1px solid #dee2e6;">
                                        Handling
                                    </th>
                                    <th rowspan="2" class="text-end" style="vertical-align:middle;">Subtotal</th>
                                    <th rowspan="2" class="text-end" style="vertical-align:middle;">SSCL</th>
                                    <th rowspan="2" class="text-end" style="vertical-align:middle;">VAT</th>
                                    <th rowspan="2" class="text-end pe-2" style="vertical-align:middle;">Grand Total</th>
                                </tr>
                                <tr>
                                    <th class="text-center bg-warning-subtle">Days</th>
                                    <th class="text-center bg-warning-subtle">Free</th>
                                    <th class="text-center bg-warning-subtle">Chgbl</th>
                                    <th class="text-end bg-warning-subtle">Amt</th>
                                    <th class="text-center bg-info-subtle">
                                        <i class="bi bi-arrow-down-circle text-success" title="Lift Off"></i>
                                    </th>
                                    <th class="text-center bg-info-subtle">
                                        <i class="bi bi-arrow-up-circle text-primary" title="Lift On"></i>
                                    </th>
                                    <th class="text-end bg-info-subtle">Amt</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                            <tfoot id="previewFoot" class="table-light fw-semibold"></tfoot>
                        </table>
                    </div>
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
                    Select a shipping line and billing period,<br>
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
const csrfToken  = '{{ csrf_token() }}';
const previewUrl = '{{ route("billing.storage-handling.preview") }}';

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
    const shippingLineId = document.getElementById('shippingLineId').value;
    const periodFrom     = document.getElementById('periodFrom').value;
    const periodTo       = document.getElementById('periodTo').value;
    const ssclPct        = document.getElementById('applySscl').checked
                           ? parseFloat(document.getElementById('ssclPct').value || 0) : 0;
    const vatPct         = document.getElementById('applyVat').checked
                           ? parseFloat(document.getElementById('vatPct').value || 0) : 0;

    if (!shippingLineId) { alert('Please select a shipping line.'); return; }
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
                shipping_line_id: shippingLineId,
                period_from: periodFrom,
                period_to:   periodTo,
                sscl_pct:    ssclPct,
                vat_pct:     vatPct,
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

    // Tariff status alerts
    const alertBox = document.getElementById('tariffAlert');
    const msgs = [];
    if (!data.storage_tariff_found) {
        msgs.push('<i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> No active <strong>storage tariff</strong> found for this shipping line. Rates from stored gate-in values will be used. <a href="{{ route("masters.storage-tariff.index") }}">Set up tariff &rarr;</a>');
    }
    if (!data.handling_tariff_found) {
        msgs.push('<i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i> No active <strong>handling tariff</strong> found for this shipping line — Lift On / Lift Off rates will be zero. <a href="{{ route("masters.handling-tariff.index") }}">Set up tariff &rarr;</a>');
    }

    if (msgs.length) {
        alertBox.className = 'alert alert-warning mb-3';
        alertBox.innerHTML = msgs.join('<hr class="my-2">');
        alertBox.classList.remove('d-none');
    } else {
        alertBox.className = 'alert alert-success d-flex align-items-center gap-2 mb-3';
        alertBox.innerHTML = '<i class="bi bi-check-circle-fill"></i> Both storage and handling tariffs loaded successfully.';
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

    const fmt  = n  => parseFloat(n).toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const icon = ok => ok ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<span class="text-muted">—</span>';

    // Summary card
    document.getElementById('sumContainers').textContent = previewLines.length;
    document.getElementById('sumStorage').textContent    = fmt(data.storage_subtotal);
    document.getElementById('sumHandling').textContent   = fmt(data.handling_subtotal);
    document.getElementById('sumSubtotal').textContent   = fmt(data.subtotal);
    document.getElementById('sumSscl').textContent       = fmt(data.sscl_amount);
    document.getElementById('sumVat').textContent        = fmt(data.vat_amount);
    document.getElementById('sumTotal').textContent      = fmt(data.total_amount);
    document.getElementById('lineCount').textContent     = previewLines.length + ' containers';

    // Lines table
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = previewLines.map((l, i) => `
        <tr>
            <td class="ps-2 text-muted">${i + 1}</td>
            <td class="font-monospace fw-semibold">${l.container_no}</td>
            <td class="text-center">
                <span class="badge bg-dark badge-size">${l.container_size || '—'}'</span>
            </td>
            <td class="small">${fmtDate(l.gate_in_date)}</td>
            <td class="text-center bg-warning-subtle">
                <span class="badge bg-light border text-dark">${l.storage_total_days}d</span>
            </td>
            <td class="text-center bg-warning-subtle text-success small">${l.storage_free_days}d</td>
            <td class="text-center bg-warning-subtle ${l.storage_chargeable_days > 0 ? 'text-danger fw-semibold' : 'text-success'}">${l.storage_chargeable_days}d</td>
            <td class="text-end bg-warning-subtle fw-semibold">${fmt(l.storage_subtotal)}</td>
            <td class="text-center bg-info-subtle">${icon(l.has_lift_off)}</td>
            <td class="text-center bg-info-subtle">${icon(l.has_lift_on)}</td>
            <td class="text-end bg-info-subtle fw-semibold">${fmt(l.handling_subtotal)}</td>
            <td class="text-end fw-semibold">${fmt(l.line_total)}</td>
            <td class="text-end small text-secondary">${fmt(l.line_sscl)}</td>
            <td class="text-end small text-secondary">${fmt(l.line_vat)}</td>
            <td class="text-end pe-2 fw-bold">${fmt(l.line_grand_total)}</td>
        </tr>
    `).join('');

    // Footer totals
    const ssclLabel = data.sscl_percentage > 0 ? `SSCL (${parseFloat(data.sscl_percentage).toFixed(2)}%)` : 'SSCL';
    const vatLabel  = data.vat_percentage  > 0 ? `VAT (${parseFloat(data.vat_percentage).toFixed(2)}%)`   : 'VAT';
    const tfoot = document.getElementById('previewFoot');
    tfoot.innerHTML = `
        <tr>
            <td class="ps-2" colspan="7" style="text-align:right">Storage Subtotal</td>
            <td class="text-end bg-warning-subtle">${fmt(data.storage_subtotal)}</td>
            <td colspan="2"></td>
            <td class="text-end bg-info-subtle">${fmt(data.handling_subtotal)}</td>
            <td class="text-end" colspan="3" style="text-align:right">Subtotal</td>
            <td class="text-end pe-2">${fmt(data.subtotal)}</td>
        </tr>
        <tr class="fw-normal text-muted">
            <td class="ps-2" colspan="14" style="text-align:right">${ssclLabel}</td>
            <td class="text-end pe-2">${fmt(data.sscl_amount)}</td>
        </tr>
        <tr class="fw-normal text-muted">
            <td class="ps-2" colspan="14" style="text-align:right">${vatLabel}</td>
            <td class="text-end pe-2">${fmt(data.vat_amount)}</td>
        </tr>
        <tr class="table-success fw-bold">
            <td class="ps-2" colspan="14" style="text-align:right">TOTAL</td>
            <td class="text-end pe-2 fs-6">${fmt(data.total_amount)}</td>
        </tr>
    `;
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

// Inject hidden inputs from preview before save
document.getElementById('billingForm').addEventListener('submit', function (e) {
    if (previewLines.length === 0) {
        e.preventDefault();
        alert('Please run a preview first.');
        return;
    }

    this.querySelectorAll('[name^="lines["], [name="sscl_percentage"], [name="vat_percentage"]')
        .forEach(el => el.remove());

    const ssclPct = document.getElementById('applySscl').checked
                    ? parseFloat(document.getElementById('ssclPct').value || 0) : 0;
    const vatPct  = document.getElementById('applyVat').checked
                    ? parseFloat(document.getElementById('vatPct').value || 0) : 0;

    const mkHidden = (name, val) => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = name; inp.value = val ?? '';
        this.appendChild(inp);
    };
    mkHidden('sscl_percentage', ssclPct);
    mkHidden('vat_percentage', vatPct);

    previewLines.forEach((line, i) => {
        Object.entries(line).forEach(([key, val]) => {
            mkHidden(`lines[${i}][${key}]`, val);
        });
    });
});
</script>
@endpush
