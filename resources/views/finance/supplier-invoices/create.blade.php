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
                <div class="col-md-4">
                    <label class="form-label small">Supplier / Contact <span class="text-danger">*</span></label>
                    <select name="customer_id" id="supplierSelect" class="form-select form-select-sm" required>
                        <option value="">— Select contact —</option>
                        @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" data-currency="{{ $sup->currency }}"
                            {{ (string) old('customer_id', request('customer_id')) === (string) $sup->id ? 'selected' : '' }}>
                            {{ $sup->code }} — {{ $sup->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Supplier's Bill No</label>
                    <input type="text" name="supplier_invoice_no" class="form-control form-control-sm" value="{{ old('supplier_invoice_no') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Invoice Date <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control form-control-sm" value="{{ old('invoice_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Due Date</label>
                    <input type="date" name="due_date" class="form-control form-control-sm" value="{{ old('due_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Currency <span class="text-danger">*</span></label>
                    <select name="currency" id="currencySelect" class="form-select form-select-sm" required>
                        @foreach(['LKR','USD','SGD'] as $c)
                        <option value="{{ $c }}" {{ old('currency','LKR') === $c ? 'selected' : '' }}>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Exchange Rate <span class="text-danger">*</span></label>
                    <input type="number" step="0.000001" min="0.000001" name="exchange_rate" class="form-control form-control-sm text-end" value="{{ old('exchange_rate', 1) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Notes</label>
                    <input type="text" name="notes" class="form-control form-control-sm" value="{{ old('notes') }}">
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

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Create Draft</button>
        <a href="{{ route('finance.ap.invoices.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
</form>

@php
    $accountOptionsHtml = '<option value="">— account —</option>';
    foreach ($accounts as $a) {
        $accountOptionsHtml .= '<option value="' . $a->id . '">' . e($a->code . ' — ' . $a->name) . '</option>';
    }
    $oldLines = old('lines', [['description' => '', 'expense_account_id' => '', 'amount' => '',
                               'charge_code_id' => '', 'tax_code_id' => '', 'tax1_rate' => 0, 'tax2_rate' => 0]]);
@endphp

@push('scripts')
<script>
(function () {
    const body         = document.getElementById('linesBody');
    const accountOpts  = @json($accountOptionsHtml);
    const chargeCodes  = @json($chargeCodes->map(fn($c) => ['id' => $c->id, 'code' => $c->code, 'description' => $c->description, 'category' => $c->category]));
    const ajaxBase     = @json(route('finance.ap.charge-code.details', ['chargeCode' => '__ID__']));
    let idx = 0;

    // Build charge code <select> options
    function buildChargeCodeOpts(selectedId) {
        let html = '<option value="">— select charge code —</option>';
        let lastCat = null;
        chargeCodes.forEach(cc => {
            if (cc.category !== lastCat) {
                if (lastCat !== null) html += '</optgroup>';
                html += `<optgroup label="${cc.category.charAt(0).toUpperCase() + cc.category.slice(1)}">`;
                lastCat = cc.category;
            }
            const sel = String(selectedId) === String(cc.id) ? ' selected' : '';
            html += `<option value="${cc.id}"${sel}>${cc.code} — ${cc.description}</option>`;
        });
        if (lastCat !== null) html += '</optgroup>';
        return html;
    }

    function buildAccountOpts(selectedId) {
        if (!selectedId) return accountOpts;
        return accountOpts.replace('value="' + selectedId + '"', 'value="' + selectedId + '" selected');
    }

    function rowHtml(i, line) {
        const desc  = (line?.description || '').replace(/"/g, '&quot;');
        const net   = line?.amount   || '';
        const t1r   = parseFloat(line?.tax1_rate ?? 0);
        const t2r   = parseFloat(line?.tax2_rate ?? 0);
        const sscl  = line?.tax1_amount != null ? parseFloat(line.tax1_amount) : 0;
        const vat   = line?.tax2_amount != null ? parseFloat(line.tax2_amount) : 0;
        const gross = line?.gross_amount != null ? parseFloat(line.gross_amount) : 0;
        const tcLabel = (line?.tax_code_code || '') ? `<span class="badge bg-secondary-subtle text-secondary font-monospace">${line.tax_code_code}</span>` : '<span class="text-muted small">—</span>';

        return `<tr>
            <td>
                <select name="lines[${i}][charge_code_id]" class="form-select form-select-sm cc-select" data-row="${i}">
                    ${buildChargeCodeOpts(line?.charge_code_id)}
                </select>
                <input type="hidden" name="lines[${i}][tax_code_id]"  class="tax-code-id"  value="${line?.tax_code_id || ''}">
                <input type="hidden" name="lines[${i}][tax1_rate]"    class="tax1-rate"    value="${t1r}">
                <input type="hidden" name="lines[${i}][tax2_rate]"    class="tax2-rate"    value="${t2r}">
            </td>
            <td><input type="text" name="lines[${i}][description]" class="form-control form-control-sm" value="${desc}" required></td>
            <td>
                <select name="lines[${i}][expense_account_id]" class="form-select form-select-sm acct-select" required>
                    ${buildAccountOpts(line?.expense_account_id)}
                </select>
            </td>
            <td class="tc-display">${tcLabel}</td>
            <td>
                <input type="number" step="0.01" min="0.01" name="lines[${i}][amount]"
                    class="form-control form-control-sm text-end font-monospace line-net"
                    value="${net}" required>
            </td>
            <td class="text-end font-monospace small text-muted line-sscl-cell">${sscl ? sscl.toFixed(2) : '0.00'}</td>
            <td class="text-end font-monospace small text-muted line-vat-cell">${vat  ? vat.toFixed(2)  : '0.00'}</td>
            <td class="text-end font-monospace small fw-semibold line-gross-cell">${gross ? gross.toFixed(2) : '0.00'}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line">
                    <i class="bi bi-x-circle"></i>
                </button>
            </td>
        </tr>`;
    }

    function addRow(line) {
        body.insertAdjacentHTML('beforeend', rowHtml(idx++, line));
        // Initialise Select2 on the newly added charge-code select
        const lastRow = body.lastElementChild;
        if (window.jQuery && window.jQuery.fn.select2) {
            jQuery(lastRow.querySelector('.cc-select')).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '— charge code —',
                allowClear: true,
            }).on('change', function () {
                handleChargeCodeChange(this);
            });
        } else {
            // Fallback: native change event
            lastRow.querySelector('.cc-select').addEventListener('change', function () {
                handleChargeCodeChange(this);
            });
        }
        recalc();
    }

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
                // Auto-fill description if currently blank
                const descInput = row.querySelector('input[name$="[description]"]');
                if (descInput && !descInput.value.trim()) {
                    descInput.value = data.description || '';
                }
                // Auto-select expense account
                if (data.expense_account_id) {
                    const acctSel = row.querySelector('.acct-select');
                    if (acctSel) acctSel.value = data.expense_account_id;
                }
                // Set hidden tax fields
                row.querySelector('.tax-code-id').value = data.tax_code_id || '';
                row.querySelector('.tax1-rate').value   = data.tax1_rate   || 0;
                row.querySelector('.tax2-rate').value   = data.tax2_rate   || 0;
                // Update tax code badge
                const tcDisplay = row.querySelector('.tc-display');
                if (tcDisplay) {
                    if (data.tax_code_code) {
                        tcDisplay.innerHTML = `<span class="badge bg-secondary-subtle text-secondary font-monospace">${data.tax_code_code}</span>`
                            + (data.tax_code_desc ? `<br><span class="text-muted" style="font-size:.7rem">${data.tax_code_desc}</span>` : '');
                    } else {
                        tcDisplay.innerHTML = '<span class="text-muted small">—</span>';
                    }
                }
                recalcRow(row);
                recalc();
            })
            .catch(() => { /* silent — user can still fill manually */ });
    }

    function resetTaxFields(row) {
        row.querySelector('.tax-code-id').value = '';
        row.querySelector('.tax1-rate').value   = 0;
        row.querySelector('.tax2-rate').value   = 0;
        const tcDisplay = row.querySelector('.tc-display');
        if (tcDisplay) tcDisplay.innerHTML = '<span class="text-muted small">—</span>';
        recalcRow(row);
    }

    function recalcRow(row) {
        const net  = parseFloat(row.querySelector('.line-net')?.value || 0);
        const t1   = parseFloat(row.querySelector('.tax1-rate')?.value || 0);
        const t2   = parseFloat(row.querySelector('.tax2-rate')?.value || 0);
        const sscl = Math.round(net * t1 / 100 * 100) / 100;
        const vat  = Math.round((net + sscl) * t2 / 100 * 100) / 100;
        const gross = Math.round((net + sscl + vat) * 100) / 100;
        row.querySelector('.line-sscl-cell').textContent  = sscl.toFixed(2);
        row.querySelector('.line-vat-cell').textContent   = vat.toFixed(2);
        row.querySelector('.line-gross-cell').textContent = gross.toFixed(2);
    }

    function recalc() {
        let subNet = 0, subSscl = 0, subVat = 0, subGross = 0;
        document.querySelectorAll('#linesBody tr').forEach(row => {
            subNet   += parseFloat(row.querySelector('.line-net')?.value        || 0);
            subSscl  += parseFloat(row.querySelector('.line-sscl-cell')?.textContent || 0);
            subVat   += parseFloat(row.querySelector('.line-vat-cell')?.textContent  || 0);
            subGross += parseFloat(row.querySelector('.line-gross-cell')?.textContent || 0);
        });
        document.getElementById('subtotalCell').textContent  = subNet.toFixed(2);
        document.getElementById('ssclTotalCell').textContent = subSscl.toFixed(2);
        document.getElementById('vatTotalCell').textContent  = subVat.toFixed(2);
        document.getElementById('grossTotalCell').textContent = subGross.toFixed(2);
        document.getElementById('totalCell').textContent     = subGross.toFixed(2);
    }

    document.getElementById('addLine').addEventListener('click', () => addRow());

    body.addEventListener('input', e => {
        const row = e.target.closest('tr');
        if (row && e.target.classList.contains('line-net')) {
            recalcRow(row);
            recalc();
        }
    });

    body.addEventListener('click', e => {
        if (e.target.closest('.remove-line')) {
            if (body.children.length > 1) e.target.closest('tr').remove();
            recalc();
        }
    });

    // Sync currency from supplier default
    const supSel = document.getElementById('supplierSelect');
    if (supSel) supSel.addEventListener('change', function () {
        const cur = this.options[this.selectedIndex]?.dataset.currency;
        if (cur) document.getElementById('currencySelect').value = cur;
    });

    // Seed rows from old() input (validation failure) or one blank row
    const seed = @json(array_values($oldLines));
    if (seed.length) seed.forEach(addRow); else addRow();
})();
</script>
@endpush

@endsection
