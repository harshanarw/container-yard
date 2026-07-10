@extends('layouts.app')

@php $isNew = !$invoice->exists; @endphp

@section('title', $isNew ? 'New General Invoice' : 'Edit '.$invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('billing.general.index') }}" class="text-decoration-none">General Invoicing</a></li>
    <li class="breadcrumb-item active">{{ $isNew ? 'New' : 'Edit' }}</li>
@endsection

@section('content')

@php
    $lineData = old('lines', $invoice->lines->map(fn($l) => [
        'charge_code_id' => $l->charge_code_id, 'description' => $l->description,
        'qty' => $l->qty, 'unit_rate' => $l->unit_rate,
        'line_currency' => $l->line_currency, 'line_exchange_rate' => $l->line_exchange_rate,
        'tax_code_id' => $l->tax_code_id,
    ])->all());
    $curCode = old('currency', $invoice->currency ?? $baseCurrency);

    // Precompute JS data here — the json blade directive can't parse arrow-fn
    // array literals inline, so build the arrays first.
    $chargeJs = $chargeCodes->map(fn($c) => [
        'id' => $c->id, 'code' => $c->code, 'desc' => $c->description,
        'tax_code_id' => $c->tax_code_id,
        't1' => (float) ($c->taxCode->tax1_rate ?? 0), 't2' => (float) ($c->taxCode->tax2_rate ?? 0),
    ])->values();
    $taxJs = $taxCodes->map(fn($t) => [
        'id' => $t->id, 'code' => $t->code, 't1' => (float) $t->tax1_rate, 't2' => (float) $t->tax2_rate,
    ])->values();
    $curJs = array_keys($currencies);
@endphp

@if($errors->any())
<div class="alert alert-danger py-2 small"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $isNew ? route('billing.general.store') : route('billing.general.update', $invoice) }}" id="giForm">
    @csrf
    @unless($isNew) @method('PATCH') @endunless

    <div class="page-header d-flex align-items-center justify-content-between">
        <h4><i class="bi bi-receipt-cutoff me-2 text-primary"></i>{{ $isNew ? 'New General Invoice' : 'Edit '.$invoice->invoice_no }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('billing.general.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>{{ $isNew ? 'Create Draft' : 'Save' }}</button>
        </div>
    </div>

    {{-- Header --}}
    <div class="card content-card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Invoice Type <span class="text-danger">*</span></label>
                    <select name="invoice_type" id="invoiceType" class="form-select" required>
                        @foreach(\App\Models\GeneralInvoice::TYPES as $k => $label)
                            <option value="{{ $k }}" {{ old('invoice_type', $invoice->invoice_type) === $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select select2">
                        <option value="">—</option>
                        @foreach(\App\Models\GeneralInvoice::CATEGORIES as $k => $label)
                            <option value="{{ $k }}" {{ old('category', $invoice->category) === $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tax Applicable <span class="text-danger">*</span></label>
                    <select name="tax_applicable" id="taxApplicable" class="form-select" required>
                        <option value="1" {{ old('tax_applicable', $invoice->tax_applicable ? '1' : '0') === '1' ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ old('tax_applicable', $invoice->tax_applicable ? '1' : '0') === '0' ? 'selected' : '' }}>No — Tax Exempt</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Reference</label>
                    <input type="text" name="reference" class="form-control" maxlength="100" value="{{ old('reference', $invoice->reference) }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerSel" class="form-select select2" required>
                        <option value="">— select —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" data-tax-exempt="{{ $c->tax_exempt ? 1 : 0 }}" {{ (string) old('customer_id', $invoice->customer_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Billing Party</label>
                    <select name="billing_party_id" id="billingPartySel" class="form-select select2">
                        <option value="">Same as customer</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" data-tax-exempt="{{ $c->tax_exempt ? 1 : 0 }}" {{ (string) old('billing_party_id', $invoice->billing_party_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" id="invoiceDate" class="form-control" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                    <select name="currency" id="invoiceCurrency" class="form-select s2-code" required>
                        @foreach($currencies as $code => $name)
                            <option value="{{ $code }}" data-code="{{ $code }}" data-name="{{ $name }}" {{ $curCode === $code ? 'selected' : '' }}>{{ $code }} — {{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Exchange Rate <span class="text-muted small" id="invRateLbl"></span></label>
                    <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="invoiceRate" class="form-control" value="{{ old('exchange_rate', $invoice->exchange_rate ?? 1) }}" required>
                    <div class="form-text" id="invRateNote">Rate of the invoice currency to base ({{ $baseCurrency }}).</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lines --}}
    <div class="card content-card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2 text-primary"></i>Line Items</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-circle me-1"></i>Add Line</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="lineTable">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:150px">Charge Code</th>
                            <th style="min-width:150px">Revenue Account</th>
                            <th style="min-width:160px">Description</th>
                            <th style="width:70px">Qty</th>
                            <th style="width:95px">Unit Rate</th>
                            <th style="width:95px">Ccy</th>
                            <th style="width:100px">Line→Inv Rate</th>
                            <th style="width:110px" class="tax-col">Tax Code</th>
                            <th style="width:100px" class="text-end">Amount</th>
                            <th style="width:100px" class="text-end">Base</th>
                            <th style="width:32px"></th>
                        </tr>
                    </thead>
                    <tbody id="lineItems"></tbody>
                    <tfoot class="table-light">
                        <tr><td colspan="8" class="text-end fw-semibold pe-3">Subtotal (<span class="cur-lbl">{{ $curCode }}</span>):</td><td class="text-end fw-semibold" id="tSub">0.00</td><td class="text-end small text-muted" id="tSubBase">0.00</td><td></td></tr>
                        <tr class="tax-row"><td colspan="8" class="text-end text-muted pe-3">SSCL:</td><td class="text-end text-muted" id="tSscl">0.00</td><td></td><td></td></tr>
                        <tr class="tax-row"><td colspan="8" class="text-end text-muted pe-3">VAT:</td><td class="text-end text-muted" id="tVat">0.00</td><td></td><td></td></tr>
                        <tr class="table-primary"><td colspan="8" class="text-end fw-bold pe-3">TOTAL (<span class="cur-lbl">{{ $curCode }}</span>):</td><td class="text-end fw-bold" id="tTotal">0.00</td><td class="text-end small" id="tTotalBase">0.00</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card content-card mb-3">
        <div class="card-body">
            <label class="form-label fw-semibold">Remarks</label>
            <textarea name="remarks" class="form-control" rows="2" maxlength="1000">{{ old('remarks', $invoice->remarks) }}</textarea>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const RATE_URL   = '{{ route("billing.general.currency-rate") }}';
    const CC_URL     = '{{ route("billing.general.charge-code-info") }}';
    const BASE       = '{{ $baseCurrency }}';
    const CHARGE     = @json($chargeJs);
    const ACCOUNTS   = @json($revenueAccounts);
    const TAXCODES   = @json($taxJs);
    const CURRENCIES = @json($curJs);
    const CURNAMES   = @json($currencies);
    const SEED       = @json($lineData);

    let idx = 0;
    const tbody = document.getElementById('lineItems');

    function esc(s){ return String(s ?? '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function invCur(){ return document.getElementById('invoiceCurrency').value || BASE; }
    function invRate(){ return parseFloat(document.getElementById('invoiceRate').value) || 1; }
    function taxOn(){ return document.getElementById('taxApplicable').value !== '0'; }

    // Charge code option → code chip + description (s2-code), carrying tax + account.
    function chargeOpts(sel){
        return '<option value="">— charge code —</option>' + CHARGE.map(c => {
            const a = ACCOUNTS[c.id];
            return `<option value="${c.id}" data-code="${esc(c.code)}" data-name="${esc(c.desc)}" data-tax="${c.tax_code_id ?? ''}" data-acode="${a?esc(a.code):''}" data-aname="${a?esc(a.name):''}" ${String(sel)===String(c.id)?'selected':''}>${esc(c.code)} — ${esc(c.desc)}</option>`;
        }).join('');
    }
    function taxOpts(sel){
        return '<option value="">— none —</option>' + TAXCODES.map(t => {
            const label = `${t.code} (SSCL ${t.t1}% + VAT ${t.t2}%)`;
            return `<option value="${t.id}" data-code="${esc(t.code)}" data-name="${esc(label)}" data-t1="${t.t1}" data-t2="${t.t2}" ${String(sel)===String(t.id)?'selected':''}>${esc(t.code)}</option>`;
        }).join('');
    }
    function curOpts(sel){ return CURRENCIES.map(c => `<option value="${c}" data-code="${c}" data-name="${esc(CURNAMES[c] || c)}" ${sel===c?'selected':''}>${c}</option>`).join(''); }

    function buildRow(d = {}) {
        const i = idx++;
        const lc = d.line_currency || invCur();
        return `<tr class="gi-line">
            <td><select name="lines[${i}][charge_code_id]" class="form-select form-select-sm s2-code charge-sel" required>${chargeOpts(d.charge_code_id)}</select></td>
            <td class="acct-cell small">—</td>
            <td><input type="text" name="lines[${i}][description]" class="form-control form-control-sm desc" value="${esc(d.description)}" required></td>
            <td><input type="number" name="lines[${i}][qty]" class="form-control form-control-sm qty" value="${d.qty ?? 1}" min="0.001" step="0.001" required></td>
            <td><input type="number" name="lines[${i}][unit_rate]" class="form-control form-control-sm rate" value="${d.unit_rate ?? 0}" min="0" step="0.01" required></td>
            <td><select name="lines[${i}][line_currency]" class="form-select form-select-sm s2-code lcur">${curOpts(lc)}</select></td>
            <td><input type="number" name="lines[${i}][line_exchange_rate]" class="form-control form-control-sm lfx" value="${d.line_exchange_rate ?? 1}" min="0.000001" step="0.000001" required></td>
            <td class="tax-col"><select name="lines[${i}][tax_code_id]" class="form-select form-select-sm s2-code taxsel">${taxOpts(d.tax_code_id)}</select></td>
            <td class="text-end small fw-semibold line-amt">0.00</td>
            <td class="text-end small text-muted line-base">0.00</td>
            <td class="text-end"><button type="button" class="btn btn-outline-danger btn-xs py-0 px-1 rm"><i class="bi bi-trash"></i></button></td>
        </tr>`;
    }

    function initRowSelects(row) {
        $(row).find('select.s2-code').each(function(){ window.initS2Code($(this), { width: '100%', dropdownParent: $('body') }); });
        $(row).find('select.select2').each(function(){ $(this).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('body') }); });
    }

    function addRow(d){
        tbody.insertAdjacentHTML('beforeend', buildRow(d));
        const row = tbody.lastElementChild;
        initRowSelects(row);
        applyCharge(row, false);   // set account cell only; keep any saved description
        return row;
    }

    // syncDesc=true copies the charge code's description into Description (on user change).
    function applyCharge(row, syncDesc) {
        const opt = row.querySelector('.charge-sel')?.selectedOptions[0];
        const acctCell = row.querySelector('.acct-cell');
        const acode = opt?.dataset.acode, aname = opt?.dataset.aname;
        if (acode) acctCell.innerHTML = `<span class="badge bg-info-subtle text-info border font-monospace">${acode}</span> <span class="text-muted">${esc(aname)}</span>`;
        else acctCell.innerHTML = (opt && opt.value) ? '<span class="badge bg-warning-subtle text-warning border">no revenue account</span>' : '<span class="text-muted">—</span>';

        if (!opt || !opt.value) return;
        if (syncDesc) { const desc = row.querySelector('.desc'); desc.value = opt.dataset.name || desc.value; }
        const taxSel = row.querySelector('.taxsel');
        const tc = opt.dataset.tax || '';
        if (tc && (syncDesc || !taxSel.value)) { taxSel.value = tc; $(taxSel).trigger('change.select2'); }
    }

    async function fetchLineFx(row) {
        const lc = row.querySelector('.lcur').value, ic = invCur();
        if (lc === ic) { row.querySelector('.lfx').value = '1'; recalc(); return; }
        try {
            const r = await fetch(`${RATE_URL}?line_currency=${lc}&invoice_currency=${ic}&date=${document.getElementById('invoiceDate').value}`);
            const j = await r.json();
            if (j.found && j.rate) row.querySelector('.lfx').value = parseFloat(j.rate).toFixed(6);
        } catch (_) {}
        recalc();
    }

    async function fetchInvoiceRate() {
        const ic = invCur();
        const note = document.getElementById('invRateNote');
        document.getElementById('invRateLbl').textContent = ic === BASE ? '' : `1 ${ic} = ? ${BASE}`;
        if (ic === BASE) { document.getElementById('invoiceRate').value = '1'; document.getElementById('invoiceRate').readOnly = true; note.textContent = `Base currency — no conversion.`; return; }
        document.getElementById('invoiceRate').readOnly = false;
        try {
            const r = await fetch(`${RATE_URL}?line_currency=${ic}&invoice_currency=${BASE}&date=${document.getElementById('invoiceDate').value}`);
            const j = await r.json();
            if (j.found && j.rate) { document.getElementById('invoiceRate').value = parseFloat(j.rate).toFixed(6); note.innerHTML = '<span class="text-success">Rate auto-loaded.</span>'; }
            else note.innerHTML = '<span class="text-warning">No rate found — enter manually.</span>';
        } catch (_) {}
    }

    function recalc() {
        const on = taxOn(); const ir = invRate();
        let sub = 0, sscl = 0, vat = 0;
        document.querySelectorAll('.gi-line').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty').value) || 0;
            const rate = parseFloat(row.querySelector('.rate').value) || 0;
            const lfx = parseFloat(row.querySelector('.lfx').value) || 1;
            const net = qty * rate * lfx;                       // invoice currency
            const opt = row.querySelector('.taxsel').selectedOptions[0];
            const t1r = on ? parseFloat(opt?.dataset.t1 || 0) : 0;
            const t2r = on ? parseFloat(opt?.dataset.t2 || 0) : 0;
            const t1 = net * t1r/100, t2 = (net + t1) * t2r/100;
            const gross = net + t1 + t2;
            sub += net; sscl += t1; vat += t2;
            row.querySelector('.line-amt').textContent = net.toFixed(2);
            row.querySelector('.line-base').textContent = (gross * ir).toFixed(2);
        });
        const total = sub + sscl + vat;
        document.getElementById('tSub').textContent = sub.toFixed(2);
        document.getElementById('tSscl').textContent = sscl.toFixed(2);
        document.getElementById('tVat').textContent = vat.toFixed(2);
        document.getElementById('tTotal').textContent = total.toFixed(2);
        document.getElementById('tSubBase').textContent = (sub * ir).toFixed(2);
        document.getElementById('tTotalBase').textContent = (total * ir).toFixed(2);
        syncTax();
    }

    function syncTax() {
        const show = taxOn();
        document.querySelectorAll('.tax-col, .tax-row').forEach(el => el.classList.toggle('d-none', !show));
        document.querySelectorAll('.cur-lbl').forEach(el => el.textContent = invCur());
    }

    // Events
    document.getElementById('addLine').addEventListener('click', () => { addRow(); recalc(); });
    tbody.addEventListener('click', e => { if (e.target.closest('.rm')) { e.target.closest('.gi-line').remove(); recalc(); } });
    tbody.addEventListener('input', e => { if (e.target.matches('.qty,.rate,.lfx')) recalc(); });
    // Select2 fires jQuery change events — delegate via jQuery so they're caught.
    $('#lineItems').on('change', 'select.charge-sel', function () { applyCharge(this.closest('tr'), true); recalc(); });
    $('#lineItems').on('change', 'select.lcur',       function () { fetchLineFx(this.closest('tr')); });
    $('#lineItems').on('change', 'select.taxsel',     function () { recalc(); });

    $('#invoiceCurrency').on('change', async function () {
        await fetchInvoiceRate();
        // Re-pull each line's cross rate against the new invoice currency.
        for (const row of document.querySelectorAll('.gi-line')) await fetchLineFx(row);
        recalc();
    });
    document.getElementById('invoiceRate').addEventListener('input', recalc);
    document.getElementById('taxApplicable').addEventListener('change', recalc);
    document.getElementById('invoiceType').addEventListener('change', function () {
        // A plain "Invoice" is non-tax by default; tax invoice / debit note default to taxed.
        document.getElementById('taxApplicable').value = this.value === 'invoice' ? '0' : '1';
        recalc();
    });

    // Default Tax Applicable from the billing party's (else the customer's) tax-exempt
    // status — the AR party drives it. Fires only on user change, so a saved value
    // isn't overwritten on edit; the user can still override afterwards.
    function autoTaxFromParty() {
        const bp   = document.getElementById('billingPartySel');
        const cust = document.getElementById('customerSel');
        const opt  = (bp && bp.value) ? bp.selectedOptions[0]
                   : ((cust && cust.value) ? cust.selectedOptions[0] : null);
        if (!opt) return;
        document.getElementById('taxApplicable').value = opt.dataset.taxExempt === '1' ? '0' : '1';
        recalc();
    }
    $('#customerSel, #billingPartySel').on('change', autoTaxFromParty);

    // Seed after DOM-ready so the layout's Select2 helpers (window.initS2Code)
    // and header select initialisation have run.
    $(function () {
        (SEED && SEED.length ? SEED : [{}]).forEach(addRow);
        fetchInvoiceRate();
        recalc();
    });
})();
</script>
@endpush

@endsection
