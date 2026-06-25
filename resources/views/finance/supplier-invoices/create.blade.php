@extends('layouts.app')

@section('title', 'New Supplier Invoice')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.ap.invoices.index') }}">Supplier Invoices</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header mb-3">
    <h4 class="mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>New Supplier Invoice</h4>
    <p class="text-muted small mb-0">Saved as a draft — approve it to post to the General Ledger.</p>
</div>

@if($errors->any())
<div class="alert alert-danger py-2 small">
    <i class="bi bi-exclamation-triangle me-1"></i>Please correct the errors below.
    <ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<form method="POST" action="{{ route('finance.ap.invoices.store') }}" id="invoiceForm">
    @csrf

    <div class="card content-card mb-3">
        <div class="card-header bg-transparent py-2"><strong class="small">Invoice Header</strong></div>
        <div class="card-body">
            <div class="row g-3">

                {{-- LEFT — Supplier & reference --}}
                <div class="col-md-6">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small">Supplier / Contact <span class="text-danger">*</span></label>
                            <select name="customer_id" id="supplierSelect" class="form-select form-select-sm" data-s2-sel="name" required>
                                <option value="">— Select contact —</option>
                                @foreach($suppliers as $sup)
                                <option value="{{ $sup->id }}"
                                    data-code="{{ $sup->code }}"
                                    data-name="{{ $sup->name }}"
                                    data-currency="{{ $sup->currency }}"
                                    data-payment-terms="{{ $sup->ap_payment_terms }}"
                                    {{ (string) old('customer_id', request('customer_id')) === (string) $sup->id ? 'selected' : '' }}>
                                    {{ $sup->code }} — {{ $sup->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Supplier's Bill No</label>
                            <input type="text" name="supplier_invoice_no" class="form-control form-control-sm"
                                value="{{ old('supplier_invoice_no') }}" placeholder="Ref / Bill number">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Supplier's Bill Date</label>
                            <input type="date" name="supplier_bill_date" id="supplierBillDate" class="form-control form-control-sm"
                                value="{{ old('supplier_bill_date') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notes</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- RIGHT — Dates & financial --}}
                <div class="col-md-6">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" name="invoice_date" id="invoiceDateInput" class="form-control form-control-sm"
                                value="{{ old('invoice_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Due Date</label>
                            <input type="date" name="due_date" id="dueDateInput" class="form-control form-control-sm"
                                value="{{ old('due_date') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Credit Terms</label>
                            <select id="creditTermsSelect" class="form-select form-select-sm">
                                <option value="">— select —</option>
                                <option value="cod">Cash on Delivery</option>
                                <option value="net15">Net 15 Days</option>
                                <option value="net30">Net 30 Days</option>
                                <option value="net45">Net 45 Days</option>
                                <option value="net60">Net 60 Days</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Currency <span class="text-danger">*</span></label>
                            <select name="currency" id="currencySelect" class="form-select form-select-sm" required>
                                @foreach(['LKR','USD','SGD'] as $c)
                                <option value="{{ $c }}" {{ old('currency','LKR') === $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small" id="exchangeRateLabel">Exchange Rate <span class="text-danger">*</span></label>
                            <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="exchangeRateInput"
                                class="form-control form-control-sm text-end font-monospace"
                                value="{{ old('exchange_rate', 1) }}" required>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="card content-card mb-3">
        <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Line Items</strong>
            <button type="button" class="btn btn-sm btn-outline-primary py-0" id="addLine"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="linesTable">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:170px">Charge Code</th>
                        <th style="min-width:200px">Description</th>
                        <th style="min-width:175px">Expense / Asset Account</th>
                        <th style="min-width:110px">Tax Code</th>
                        <th class="text-end" style="min-width:110px">Net Amount</th>
                        <th class="text-end" style="min-width:90px">SSCL</th>
                        <th class="text-end" style="min-width:90px">VAT</th>
                        <th class="text-end" style="min-width:110px">Gross</th>
                        <th style="width:36px"></th>
                    </tr>
                </thead>
                <tbody id="linesBody"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end small text-muted">Subtotal (net)</td>
                        <td class="text-end font-monospace fw-semibold" id="subtotalCell">0.00</td>
                        <td class="text-end font-monospace text-muted" id="ssclTotalCell">0.00</td>
                        <td class="text-end font-monospace text-muted" id="vatTotalCell">0.00</td>
                        <td class="text-end font-monospace fw-semibold" id="grossTotalCell">0.00</td>
                        <td></td>
                    </tr>
                    <tr class="table-light fw-bold">
                        <td colspan="7" class="text-end">Total Payable</td>
                        <td class="text-end font-monospace" id="totalCell">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Create Draft</button>
        <a href="{{ route('finance.ap.invoices.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        <span class="text-muted small ms-2"><i class="bi bi-paperclip me-1"></i>You can attach supporting documents (bills, PDFs) after saving.</span>
    </div>
</form>

@php
    $accountOptionsHtml = '<option value="">— account —</option>';
    foreach ($accounts as $a) {
        $accountOptionsHtml .= '<option value="' . $a->id . '" data-code="' . e($a->code) . '" data-name="' . e($a->name) . '">'
            . e($a->code . ' — ' . $a->name) . '</option>';
    }
    $chargeCodesData = $chargeCodes->map(fn($c) => [
        'id'          => $c->id,
        'code'        => $c->code,
        'description' => $c->description,
        'category'    => $c->category,
    ])->values()->all();
    $taxCodesData = $taxCodes->map(fn($t) => [
        'id'        => $t->id,
        'code'      => $t->code,
        'description' => $t->description,
        'tax1_rate' => $t->tax1_rate,
        'tax2_rate' => $t->tax2_rate,
    ])->values()->all();
    $oldLines = old('lines', [['description' => '', 'expense_account_id' => '', 'amount' => '',
                               'charge_code_id' => '', 'tax_code_id' => '', 'tax1_rate' => 0, 'tax2_rate' => 0]]);
@endphp

@push('scripts')
<script>
(function () {
    const body        = document.getElementById('linesBody');
    const accountOpts = @json($accountOptionsHtml);
    const chargeCodes = @json($chargeCodesData);
    const taxCodes    = @json($taxCodesData);
    const ajaxBase    = @json(route('finance.ap.charge-code.details', ['chargeCode' => '__ID__']));
    let idx = 0;

    // Build grouped structure once — keeps code + name separate for chip styling.
    const ccGroups = (() => {
        const groups = {};
        chargeCodes.forEach(cc => {
            const cat   = cc.category || 'general';
            const label = cat.charAt(0).toUpperCase() + cat.slice(1);
            if (!groups[cat]) groups[cat] = { label, items: [] };
            groups[cat].items.push({ id: cc.id, code: cc.code, name: cc.description });
        });
        return Object.values(groups);
    })();

    // Populate charge code <select> with grouped DOM options.
    // Select2 on a <select> reads from the DOM; data-code/data-name enable chip templates.
    function populateCcOptions(selectEl) {
        ccGroups.forEach(group => {
            const og = document.createElement('optgroup');
            og.label = group.label;
            group.items.forEach(item => {
                const opt        = document.createElement('option');
                opt.value        = item.id;
                opt.textContent  = item.code + ' — ' + item.name;
                opt.dataset.code = item.code;
                opt.dataset.name = item.name;
                og.appendChild(opt);
            });
            selectEl.appendChild(og);
        });
    }

    // Populate tax code <select> with flat DOM options.
    // data-code/data-name enable the layout's s2CodeResult/s2CodeSelection chip templates.
    function populateTcOptions(selectEl, selectedId) {
        taxCodes.forEach(tc => {
            const opt        = document.createElement('option');
            opt.value        = tc.id;
            opt.textContent  = tc.code + ' — ' + tc.description;
            opt.dataset.code = tc.code;
            opt.dataset.name = tc.description;
            opt.dataset.t1   = tc.tax1_rate;
            opt.dataset.t2   = tc.tax2_rate;
            if (selectedId && String(tc.id) === String(selectedId)) opt.selected = true;
            selectEl.appendChild(opt);
        });
    }

    function buildAccountOpts(selectedId) {
        if (!selectedId) return accountOpts;
        return accountOpts.replace('value="' + selectedId + '"', 'value="' + selectedId + '" selected');
    }

    function rowHtml(i, line) {
        const desc = (line?.description || '').replace(/"/g, '&quot;');
        const net  = line?.amount || '';
        const t1r  = parseFloat(line?.tax1_rate ?? 0);
        const t2r  = parseFloat(line?.tax2_rate ?? 0);

        return `<tr>
            <td>
                <select name="lines[${i}][charge_code_id]" class="form-select form-select-sm cc-select">
                    <option value=""></option>
                </select>
                <input type="hidden" name="lines[${i}][tax1_rate]" class="tax1-rate" value="${t1r}">
                <input type="hidden" name="lines[${i}][tax2_rate]" class="tax2-rate" value="${t2r}">
            </td>
            <td><input type="text" name="lines[${i}][description]" class="form-control form-control-sm" value="${desc}" required></td>
            <td>
                <select name="lines[${i}][expense_account_id]" class="form-select form-select-sm acct-select" data-s2-sel="name" required>
                    ${buildAccountOpts(line?.expense_account_id)}
                </select>
            </td>
            <td>
                <select name="lines[${i}][tax_code_id]" class="form-select form-select-sm tc-select">
                    <option value="">— none —</option>
                </select>
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" name="lines[${i}][amount]"
                    class="form-control form-control-sm text-end font-monospace line-net"
                    value="${net}" required>
            </td>
            <td class="text-end font-monospace small text-muted line-sscl-cell">0.00</td>
            <td class="text-end font-monospace small text-muted line-vat-cell">0.00</td>
            <td class="text-end font-monospace small fw-semibold line-gross-cell">0.00</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line">
                    <i class="bi bi-x-circle"></i>
                </button>
            </td>
        </tr>`;
    }

    function initRow(row, savedCcId, savedTcId) {
        // ── Charge code Select2 ──────────────────────────────────────────────
        const ccEl = row.querySelector('.cc-select');
        populateCcOptions(ccEl);

        const $ccSel = jQuery(ccEl).select2({
            theme             : 'bootstrap-5',
            width             : '100%',
            placeholder       : '— charge code —',
            allowClear        : true,
            templateResult    : window.s2CodeResult    || null,
            templateSelection : window.s2CodeSelection || null,
        });

        if (savedCcId) $ccSel.val(String(savedCcId)).trigger('change.select2');
        $ccSel.on('change', function () { handleChargeCodeChange(this); });

        // ── Tax code Select2 ─────────────────────────────────────────────────
        const tcEl = row.querySelector('.tc-select');
        populateTcOptions(tcEl, savedTcId);

        const $tcSel = jQuery(tcEl).select2({
            theme             : 'bootstrap-5',
            width             : '100%',
            placeholder       : '— none —',
            allowClear        : true,
            templateResult    : window.s2CodeResult    || null,
            templateSelection : window.s2CodeSelection || null,
        });
        $tcSel.on('change', function () { handleTaxCodeChange(this); });

        // ── Account Select2 ──────────────────────────────────────────────────
        jQuery(row.querySelector('.acct-select')).select2({
            theme             : 'bootstrap-5',
            width             : '100%',
            placeholder       : '— account —',
            templateResult    : window.s2CodeResult    || null,
            templateSelection : window.s2CodeSelection || null,
        });
    }

    function addRow(line) {
        body.insertAdjacentHTML('beforeend', rowHtml(idx++, line));
        const lastRow  = body.lastElementChild;
        const savedCcId = line?.charge_code_id || '';
        const savedTcId = line?.tax_code_id    || '';

        initRow(lastRow, savedCcId, savedTcId);

        if (line && (parseFloat(line.tax1_rate) > 0 || parseFloat(line.tax2_rate) > 0)) {
            recalcRow(lastRow);
        }
        recalc();
    }

    // When user picks a charge code, fetch defaults and populate description,
    // account, and tax code. Description always syncs to the selected charge code.
    function handleChargeCodeChange(selectEl) {
        const ccId = selectEl.value;
        const row  = selectEl.closest('tr');
        if (!ccId) {
            resetTaxFields(row);
            recalc();
            return;
        }
        const url = ajaxBase.replace('__ID__', ccId);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                // Always update description from charge code when charge code changes
                const descInput = row.querySelector('input[name$="[description]"]');
                if (descInput) descInput.value = data.description || '';

                if (data.expense_account_id) {
                    jQuery(row.querySelector('.acct-select'))
                        .val(data.expense_account_id).trigger('change.select2');
                }

                // Set tax code dropdown — triggers handleTaxCodeChange which updates rates
                jQuery(row.querySelector('.tc-select'))
                    .val(data.tax_code_id || '').trigger('change');
            })
            .catch(() => { /* silent — user can fill manually */ });
    }

    // When user changes the tax code dropdown, look up rates from JS array and recalc.
    function handleTaxCodeChange(selectEl) {
        const row = selectEl.closest('tr');
        const tc  = taxCodes.find(t => String(t.id) === String(selectEl.value));
        row.querySelector('.tax1-rate').value = tc ? tc.tax1_rate : 0;
        row.querySelector('.tax2-rate').value = tc ? tc.tax2_rate : 0;
        recalcRow(row);
        recalc();
    }

    function resetTaxFields(row) {
        jQuery(row.querySelector('.tc-select')).val('').trigger('change');
    }

    function recalcRow(row) {
        const net  = parseFloat(row.querySelector('.line-net')?.value  || 0);
        const t1   = parseFloat(row.querySelector('.tax1-rate')?.value || 0);
        const t2   = parseFloat(row.querySelector('.tax2-rate')?.value || 0);
        const sscl  = Math.round(net * t1 / 100 * 100) / 100;
        const vat   = Math.round((net + sscl) * t2 / 100 * 100) / 100;
        const gross = Math.round((net + sscl + vat) * 100) / 100;
        row.querySelector('.line-sscl-cell').textContent  = sscl.toFixed(2);
        row.querySelector('.line-vat-cell').textContent   = vat.toFixed(2);
        row.querySelector('.line-gross-cell').textContent = gross.toFixed(2);
    }

    function recalc() {
        let subNet = 0, subSscl = 0, subVat = 0, subGross = 0;
        document.querySelectorAll('#linesBody tr').forEach(row => {
            subNet   += parseFloat(row.querySelector('.line-net')?.value              || 0);
            subSscl  += parseFloat(row.querySelector('.line-sscl-cell')?.textContent  || 0);
            subVat   += parseFloat(row.querySelector('.line-vat-cell')?.textContent   || 0);
            subGross += parseFloat(row.querySelector('.line-gross-cell')?.textContent || 0);
        });
        document.getElementById('subtotalCell').textContent   = subNet.toFixed(2);
        document.getElementById('ssclTotalCell').textContent  = subSscl.toFixed(2);
        document.getElementById('vatTotalCell').textContent   = subVat.toFixed(2);
        document.getElementById('grossTotalCell').textContent = subGross.toFixed(2);
        document.getElementById('totalCell').textContent      = subGross.toFixed(2);
    }

    document.getElementById('addLine').addEventListener('click', () => addRow());

    body.addEventListener('input', e => {
        const row = e.target.closest('tr');
        if (row && e.target.classList.contains('line-net')) { recalcRow(row); recalc(); }
    });

    body.addEventListener('click', e => {
        if (e.target.closest('.remove-line')) {
            const row = e.target.closest('tr');
            if (body.children.length > 1) {
                jQuery(row.querySelector('.cc-select')).select2('destroy');
                jQuery(row.querySelector('.tc-select')).select2('destroy');
                jQuery(row.querySelector('.acct-select')).select2('destroy');
                row.remove();
            }
            recalc();
        }
    });

    // ── Header-level logic (supplier, dates, exchange rate) ─────────────────

    const termDays = { cod: 0, net15: 15, net30: 30, net45: 45, net60: 60 };

    function calcDueDate() {
        const terms = document.getElementById('creditTermsSelect').value;
        if (!terms) return;
        const days    = Object.prototype.hasOwnProperty.call(termDays, terms) ? termDays[terms] : 0;
        const dateVal = document.getElementById('invoiceDateInput').value;
        if (!dateVal) return;
        const d = new Date(dateVal);
        d.setDate(d.getDate() + days);
        document.getElementById('dueDateInput').value = d.toISOString().slice(0, 10);
    }

    function updateExchangeRateLabel() {
        const ccy      = document.getElementById('currencySelect').value;
        const labelEl  = document.getElementById('exchangeRateLabel');
        const rateInput = document.getElementById('exchangeRateInput');
        if (!ccy || ccy === 'LKR') {
            labelEl.innerHTML = 'Exchange Rate <span class="text-muted small fw-normal">(LKR — base currency)</span>';
            rateInput.value    = '1';
            rateInput.readOnly = true;
            rateInput.classList.add('bg-light', 'text-muted');
        } else {
            labelEl.innerHTML = ccy + ' → LKR Rate <span class="text-danger">*</span>';
            rateInput.readOnly = false;
            rateInput.classList.remove('bg-light', 'text-muted');
        }
    }

    // Defer header Select2 and event wiring to DOMContentLoaded so
    // window.s2CodeResult / s2CodeSelection are defined by the layout's handler first.
    document.addEventListener('DOMContentLoaded', function () {
        // Supplier Select2
        jQuery('#supplierSelect').select2({
            theme             : 'bootstrap-5',
            width             : '100%',
            placeholder       : '— Select contact —',
            templateResult    : window.s2CodeResult    || null,
            templateSelection : window.s2CodeSelection || null,
        });

        jQuery('#supplierSelect').on('change', function () {
            const opt = this.options[this.selectedIndex];
            const cur = opt?.dataset.currency;
            if (cur) {
                document.getElementById('currencySelect').value = cur;
                updateExchangeRateLabel();
            }
            const terms = opt?.dataset.paymentTerms || '';
            document.getElementById('creditTermsSelect').value = terms;
            calcDueDate();
        });

        document.getElementById('creditTermsSelect').addEventListener('change', calcDueDate);
        document.getElementById('invoiceDateInput').addEventListener('change', calcDueDate);
        document.getElementById('currencySelect').addEventListener('change', updateExchangeRateLabel);

        // Initialise exchange rate label (and credit terms if supplier restored after failed submit).
        updateExchangeRateLabel();
        const supSel = document.getElementById('supplierSelect');
        const restoredTerms = supSel.options[supSel.selectedIndex]?.dataset.paymentTerms || '';
        if (restoredTerms) document.getElementById('creditTermsSelect').value = restoredTerms;

        // Seed line rows (must come after DOMContentLoaded for s2CodeResult)
        const seed = @json(array_values($oldLines));
        if (seed.length) seed.forEach(line => addRow(line)); else addRow();
    });
})();
</script>
@endpush

@endsection
