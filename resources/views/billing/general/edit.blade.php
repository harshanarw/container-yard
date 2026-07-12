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
        'charge_code_id' => $l->charge_code_id, 'revenue_account_id' => $l->revenue_account_id,
        'description' => $l->description,
        'qty' => $l->qty, 'unit_rate' => $l->unit_rate,
        'line_currency' => $l->line_currency, 'line_exchange_rate' => $l->line_exchange_rate,
        'tax_code_id' => $l->tax_code_id, 'yard_job_id' => $l->yard_job_id,
    ])->all());
    $acctJs = $incomeAccounts->map(fn($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name])->values();
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
            @php
                $lblCls = 'col-sm-4 col-form-label fw-semibold text-sm-end';
                $curTerms = old('payment_terms', $invoice->payment_terms ?? 'net30');
            @endphp
            <div class="row g-3">
                {{-- ① Billing Parties — chosen first; they drive Tax, Type, Credit Term & Currency defaults --}}
                <div class="col-12">
                    <div class="text-primary fw-semibold small text-uppercase border-bottom pb-1">
                        <i class="bi bi-people me-1"></i>Billing Parties
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="customerSel">Customer <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="customer_id" id="customerSel" class="form-select select2 s2-code" data-s2-sel="name" required>
                                <option value="">— select —</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" data-tax-exempt="{{ $c->tax_exempt ? 1 : 0 }}" data-terms="{{ $c->payment_terms }}" data-currency="{{ $c->currency }}" {{ (string) old('customer_id', $invoice->customer_id) === (string) $c->id ? 'selected' : '' }}>[{{ $c->code }}] {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="billingPartySel">Billing Party</label>
                        <div class="col-sm-8">
                            <select name="billing_party_id" id="billingPartySel" class="form-select select2 s2-code" data-s2-sel="name">
                                <option value="">Same as customer</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" data-tax-exempt="{{ $c->tax_exempt ? 1 : 0 }}" data-terms="{{ $c->payment_terms }}" data-currency="{{ $c->currency }}" {{ (string) old('billing_party_id', $invoice->billing_party_id) === (string) $c->id ? 'selected' : '' }}>[{{ $c->code }}] {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- ② Invoice Details --}}
                <div class="col-12">
                    <div class="text-primary fw-semibold small text-uppercase border-bottom pb-1">
                        <i class="bi bi-receipt me-1"></i>Invoice Details
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="invoiceType">Invoice Type <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="invoice_type" id="invoiceType" class="form-select" required>
                                @foreach(\App\Models\GeneralInvoice::TYPES as $k => $label)
                                    <option value="{{ $k }}" {{ old('invoice_type', $invoice->invoice_type) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="taxApplicable">Tax Applicable <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="tax_applicable" id="taxApplicable" class="form-select" required>
                                <option value="1" {{ old('tax_applicable', $invoice->tax_applicable ? '1' : '0') === '1' ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ old('tax_applicable', $invoice->tax_applicable ? '1' : '0') === '0' ? 'selected' : '' }}>No — Tax Exempt</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="category">Category</label>
                        <div class="col-sm-8">
                            <select name="category" id="category" class="form-select select2">
                                <option value="">—</option>
                                @foreach(\App\Models\GeneralInvoice::CATEGORIES as $k => $label)
                                    <option value="{{ $k }}" {{ old('category', $invoice->category) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="reference">Reference</label>
                        <div class="col-sm-8">
                            <input type="text" name="reference" id="reference" class="form-control" maxlength="100" value="{{ old('reference', $invoice->reference) }}">
                        </div>
                    </div>
                </div>

                {{-- Job costing — tag this invoice's income to a job/container --}}
                @php
                    $jobLabel = function ($j) {
                        $parts = [$j['job_no']];
                        if (!empty($j['container_no'])) $parts[] = $j['container_no'];
                        if (!empty($j['size']))         $parts[] = $j['size']."'".$j['type_code'];
                        if (!empty($j['customer']))     $parts[] = $j['customer'];
                        return implode('  ·  ', $parts);
                    };
                @endphp
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="yard_job_id">Job <span class="text-muted fw-normal">(costing)</span></label>
                        <div class="col-sm-8">
                            <select name="yard_job_id" id="yard_job_id" class="form-select select2 job-select">
                                <option value="">— None —</option>
                                @foreach($jobs as $j)
                                    <option value="{{ $j['id'] }}" data-cust-id="{{ $j['customer_id'] }}" @selected(old('yard_job_id') == $j['id'])>{{ $jobLabel($j) }}</option>
                                @endforeach
                            </select>
                            <div class="form-text d-flex justify-content-between align-items-center flex-wrap">
                                <span>Sets the job for <strong>all lines</strong>. Leave blank to tag lines individually.</span>
                                <label class="small mb-0"><input type="checkbox" id="jobShowAll" class="form-check-input me-1"> Show all jobs</label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ③ Currency --}}
                <div class="col-12">
                    <div class="text-primary fw-semibold small text-uppercase border-bottom pb-1">
                        <i class="bi bi-currency-exchange me-1"></i>Currency
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="invoiceCurrency">Currency <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="currency" id="invoiceCurrency" class="form-select s2-code" required>
                                @foreach($currencies as $code => $name)
                                    <option value="{{ $code }}" data-code="{{ $code }}" data-name="{{ $name }}" {{ $curCode === $code ? 'selected' : '' }}>{{ $code }} — {{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="invoiceRate">Exchange Rate <span class="text-muted small fw-normal" id="invRateLbl"></span></label>
                        <div class="col-sm-8">
                            <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="invoiceRate" class="form-control" value="{{ old('exchange_rate', $invoice->exchange_rate ?? 1) }}" required>
                            <div class="form-text" id="invRateNote">Rate of the invoice currency to base ({{ $baseCurrency }}).</div>
                        </div>
                    </div>
                </div>

                {{-- ④ Dates & Credit Terms --}}
                <div class="col-12">
                    <div class="text-primary fw-semibold small text-uppercase border-bottom pb-1">
                        <i class="bi bi-calendar-event me-1"></i>Dates &amp; Credit Terms
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="invoiceDate">Invoice Date <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="date" name="invoice_date" id="invoiceDate" class="form-control" value="{{ old('invoice_date', optional($invoice->invoice_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="creditTerm">Credit Term</label>
                        <div class="col-sm-8">
                            <select name="payment_terms" id="creditTerm" class="form-select">
                                @foreach(['cod' => 'Cash on Delivery', 'net15' => 'Net 15 Days', 'net30' => 'Net 30 Days', 'net45' => 'Net 45 Days', 'net60' => 'Net 60 Days'] as $k => $label)
                                    <option value="{{ $k }}" {{ $curTerms === $k ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row">
                        <label class="{{ $lblCls }}" for="dueDate">Due Date</label>
                        <div class="col-sm-8">
                            <input type="date" name="due_date" id="dueDate" class="form-control" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d')) }}">
                        </div>
                    </div>
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
                <table class="table align-middle mb-0" id="lineTable" style="table-layout:fixed; width:100%; min-width:1130px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:105px">Charge Code</th>
                            <th style="width:150px">Revenue Account</th>
                            <th style="width:170px">Description</th>
                            <th style="width:130px">Job <span class="text-muted fw-normal" style="font-size:.7rem;">(costing)</span></th>
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
                        <tr><td colspan="8" class="foot-span text-end fw-semibold pe-3">Subtotal (<span class="cur-lbl">{{ $curCode }}</span>):</td><td class="text-end fw-semibold" id="tSub">0.00</td><td class="text-end small text-muted" id="tSubBase">0.00</td><td></td></tr>
                        <tr class="tax-row"><td colspan="8" class="text-end text-muted pe-3">SSCL:</td><td class="text-end text-muted" id="tSscl">0.00</td><td></td><td></td></tr>
                        <tr class="tax-row"><td colspan="8" class="text-end text-muted pe-3">VAT:</td><td class="text-end text-muted" id="tVat">0.00</td><td></td><td></td></tr>
                        <tr class="table-primary"><td colspan="8" class="foot-span text-end fw-bold pe-3">TOTAL (<span class="cur-lbl">{{ $curCode }}</span>):</td><td class="text-end fw-bold" id="tTotal">0.00</td><td class="text-end small" id="tTotalBase">0.00</td><td></td></tr>
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
    const INCOME_ACCTS = @json($acctJs);
    const TAXCODES   = @json($taxJs);
    const CURRENCIES = @json($curJs);
    const CURNAMES   = @json($currencies);
    const JOBS       = @json($jobs);
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
    // Per-line job options — rich label (Job · Container · Size/Type · Customer),
    // carrying data-cust-id for the party soft-filter.
    function jobLabel(j){
        let p = [j.job_no];
        if (j.container_no) p.push(j.container_no);
        if (j.size) p.push(j.size + "'" + (j.type_code || ''));
        if (j.customer) p.push(j.customer);
        return p.join('  ·  ');
    }
    function jobOpts(sel){
        return '<option value="">— none —</option>' + JOBS.map(j =>
            `<option value="${j.id}" data-cust-id="${j.customer_id ?? ''}" ${String(sel)===String(j.id)?'selected':''}>${esc(jobLabel(j))}</option>`
        ).join('');
    }
    function acctOpts(sel){
        return '<option value="">— revenue a/c —</option>' + INCOME_ACCTS.map(a =>
            `<option value="${a.id}" data-code="${esc(a.code)}" data-name="${esc(a.name)}" ${String(sel)===String(a.id)?'selected':''}>${esc(a.code)} — ${esc(a.name)}</option>`
        ).join('');
    }

    function buildRow(d = {}) {
        const i = idx++;
        const lc = d.line_currency || invCur();
        return `<tr class="gi-line">
            <td><select name="lines[${i}][charge_code_id]" class="form-select form-select-sm s2-code charge-sel" required>${chargeOpts(d.charge_code_id)}</select></td>
            <td><select name="lines[${i}][revenue_account_id]" class="form-select form-select-sm s2-code acct-sel" data-s2-sel="name">${acctOpts(d.revenue_account_id)}</select></td>
            <td><input type="text" name="lines[${i}][description]" class="form-control form-control-sm desc" value="${esc(d.description)}" required></td>
            <td><select name="lines[${i}][yard_job_id]" class="form-select form-select-sm select2 job-line">${jobOpts(d.yard_job_id)}</select></td>
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
        // New line's job select respects the active party filter + header default.
        $(row).find('select.job-line').each(function(){
            rebuildJobSelect($(this));
            const hv = document.getElementById('yard_job_id')?.value;
            if (hv && !this.value) $(this).val(hv).trigger('change.select2');
        });
    }

    // ── Job costing: party soft-filter + header→line inheritance ──────────────
    function jobPartyId(){ return (document.getElementById('billingPartySel')?.value) || (document.getElementById('customerSel')?.value) || ''; }
    function jobShowAll(){ return !!document.getElementById('jobShowAll')?.checked; }
    function jobsForParty(){
        const p = jobPartyId();
        if (!p || jobShowAll()) return JOBS;
        return JOBS.filter(j => String(j.customer_id) === String(p));
    }
    function rebuildJobSelect($sel){
        const cur = $sel.val();
        let list = jobsForParty().slice();
        // Always keep the current selection visible even if filtered out.
        if (cur && !list.some(j => String(j.id) === String(cur))) {
            const f = JOBS.find(j => String(j.id) === String(cur));
            if (f) list.unshift(f);
        }
        const label = $sel.hasClass('job-line') ? 'none' : 'None';
        $sel.html('<option value="">— ' + label + ' —</option>' + list.map(j =>
            `<option value="${j.id}" data-cust-id="${j.customer_id ?? ''}">${esc(jobLabel(j))}</option>`
        ).join('')).val(cur || '').trigger('change.select2');
    }
    function refreshAllJobSelects(){
        rebuildJobSelect($('#yard_job_id'));
        $('select.job-line').each(function(){ rebuildJobSelect($(this)); });
    }

    function addRow(d){
        tbody.insertAdjacentHTML('beforeend', buildRow(d));
        const row = tbody.lastElementChild;
        initRowSelects(row);
        applyCharge(row, false);   // set account cell only; keep any saved description
        return row;
    }

    // On charge-code select: default the revenue account to the charge's mapped
    // account (overridable), and on a user change also sync description + tax code.
    // syncDesc=true → user-initiated change; false → initial seed (keep saved values).
    function applyCharge(row, syncDesc) {
        const opt = row.querySelector('.charge-sel')?.selectedOptions[0];
        if (!opt || !opt.value) return;

        const acctSel = row.querySelector('.acct-sel');
        const mapped  = ACCOUNTS[opt.value];   // { id, code, name } or undefined
        if (acctSel && mapped && mapped.id && (syncDesc || !acctSel.value)) {
            acctSel.value = mapped.id;
            $(acctSel).trigger('change.select2');
        }

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
        // The footer label spans every column left of Amount. Hiding the Tax
        // column drops the row to 10 columns, so the label must span 7 (not 8)
        // to keep Amount / Base / trash aligned under their headers.
        document.querySelectorAll('.foot-span').forEach(td => td.colSpan = show ? 8 : 7);
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

    // A line the user hasn't touched yet: no charge, blank description, zero rate.
    function isLineEmpty(row) {
        return !row.querySelector('.charge-sel').value
            && !row.querySelector('.desc').value.trim()
            && (parseFloat(row.querySelector('.rate').value) || 0) === 0;
    }
    $('#invoiceCurrency').on('change', async function () {
        await fetchInvoiceRate();
        const ic = invCur();
        for (const row of document.querySelectorAll('.gi-line')) {
            if (isLineEmpty(row)) {
                // Untouched line — follow the new invoice currency (1:1 cross rate).
                const lc = row.querySelector('.lcur');
                lc.value = ic; $(lc).trigger('change.select2');
                row.querySelector('.lfx').value = '1';
            } else {
                // Populated line keeps its own currency; re-pull the cross rate.
                await fetchLineFx(row);
            }
        }
        recalc();
    });
    document.getElementById('invoiceRate').addEventListener('input', recalc);
    document.getElementById('taxApplicable').addEventListener('change', function () {
        // Tax Applicable drives the document type: Yes → Tax Invoice, No →
        // Invoice. A Debit Note is a distinct document type, so leave it be.
        const it = document.getElementById('invoiceType');
        if (it.value !== 'debit_note') it.value = this.value === '1' ? 'tax_invoice' : 'invoice';
        recalc();
    });
    document.getElementById('invoiceType').addEventListener('change', function () {
        // A plain "Invoice" is non-tax by default; tax invoice / debit note default to taxed.
        document.getElementById('taxApplicable').value = this.value === 'invoice' ? '0' : '1';
        recalc();
    });

    // Default Tax Applicable from the billing party's (else the customer's) tax-exempt
    // status — the AR party drives it. Fires only on user change, so a saved value
    // isn't overwritten on edit; the user can still override afterwards.
    function partyOpt() {
        const bp   = document.getElementById('billingPartySel');
        const cust = document.getElementById('customerSel');
        return (bp && bp.value) ? bp.selectedOptions[0]
             : ((cust && cust.value) ? cust.selectedOptions[0] : null);
    }
    function autoTaxFromParty() {
        const opt = partyOpt();
        if (!opt) return;
        const exempt = opt.dataset.taxExempt === '1';
        document.getElementById('taxApplicable').value = exempt ? '0' : '1';
        // Invoice type default follows tax status: a tax-exempt party can't be
        // issued a Tax Invoice, a taxable party defaults to one. Debit Note is a
        // distinct document type, so leave it untouched.
        const it = document.getElementById('invoiceType');
        if (exempt && it.value === 'tax_invoice') it.value = 'invoice';
        else if (!exempt && it.value === 'invoice') it.value = 'tax_invoice';
        recalc();
    }

    // Credit term → due date. Picking a party pulls its profile term; changing
    // the term or invoice date re-derives the due date. Due date stays editable.
    const TERM_DAYS = { cod: 0, net15: 15, net30: 30, net45: 45, net60: 60 };
    function recomputeDueDate() {
        const inv = document.getElementById('invoiceDate').value; // YYYY-MM-DD
        if (!inv) return;
        const days = TERM_DAYS[document.getElementById('creditTerm').value] ?? 30;
        const [y, m, d] = inv.split('-').map(Number);
        // Compute in UTC so the result never shifts a day in +TZ locales.
        const dt = new Date(Date.UTC(y, m - 1, d));
        dt.setUTCDate(dt.getUTCDate() + days);
        document.getElementById('dueDate').value = dt.toISOString().slice(0, 10);
    }
    function creditTermFromParty() {
        const opt = partyOpt();
        const t = opt && opt.dataset.terms;
        if (t && TERM_DAYS.hasOwnProperty(t)) document.getElementById('creditTerm').value = t;
        recomputeDueDate();
    }

    // Default the invoice currency from the AR party's master currency. Only
    // when no line data has been entered yet, so we never silently re-denominate
    // an invoice mid-edit. Triggers the currency change handler (rate + empty
    // line propagation). Stays overridable afterwards.
    function currencyFromParty() {
        const opt = partyOpt();
        const cur = opt && opt.dataset.currency;
        if (!cur) return;
        const anyData = [...document.querySelectorAll('.gi-line')].some(r => !isLineEmpty(r));
        if (anyData) return;
        const sel = document.getElementById('invoiceCurrency');
        if (sel.value === cur) return;
        $(sel).val(cur).trigger('change');
    }
    $('#customerSel, #billingPartySel').on('change', function () {
        autoTaxFromParty();
        creditTermFromParty();
        currencyFromParty();
        refreshAllJobSelects();   // re-filter jobs to the selected party
    });
    $('#jobShowAll').on('change', refreshAllJobSelects);
    // Header job → set every line's job (still editable per line afterwards).
    $('#yard_job_id').on('change', function () {
        const v = this.value;
        if (v) $('select.job-line').each(function () { $(this).val(v).trigger('change.select2'); });
    });
    document.getElementById('creditTerm').addEventListener('change', recomputeDueDate);
    document.getElementById('invoiceDate').addEventListener('change', recomputeDueDate);

    // Seed after DOM-ready so the layout's Select2 helpers (window.initS2Code)
    // and header select initialisation have run.
    $(function () {
        (SEED && SEED.length ? SEED : [{}]).forEach(addRow);
        refreshAllJobSelects();   // apply the party filter to the header + seeded lines
        fetchInvoiceRate();
        recalc();
    });
})();
</script>
@endpush

@endsection
