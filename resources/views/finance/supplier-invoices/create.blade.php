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
                    <label class="form-label small">Supplier <span class="text-danger">*</span></label>
                    <select name="supplier_id" id="supplierSelect" class="form-select form-select-sm" required>
                        <option value="">— Select supplier —</option>
                        @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" data-currency="{{ $sup->currency }}"
                            {{ (string) old('supplier_id', request('supplier_id')) === (string) $sup->id ? 'selected' : '' }}>
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
                    <div class="form-text small">Base units per 1 {{ '' }}invoice-currency unit.</div>
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
                        <th style="width:40%">Description</th>
                        <th style="width:35%">Expense / Asset Account</th>
                        <th class="text-end" style="width:20%">Amount (net)</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="linesBody"><!-- rows injected by JS --></tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end small text-muted">Subtotal</td>
                        <td class="text-end font-monospace fw-semibold" id="subtotalCell">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-end small text-muted">Input Tax</td>
                        <td class="text-end">
                            <input type="number" step="0.01" min="0" name="tax_amount" id="taxAmount" class="form-control form-control-sm text-end font-monospace" value="{{ old('tax_amount', 0) }}">
                        </td>
                        <td></td>
                    </tr>
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="text-end">Total Payable</td>
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
    $accountOptions = '<option value="">— account —</option>';
    foreach ($accounts as $a) {
        $accountOptions .= '<option value="' . $a->id . '">' . e($a->code . ' — ' . $a->name) . '</option>';
    }
    $oldLines = old('lines', [['description' => '', 'expense_account_id' => '', 'amount' => '']]);
@endphp

@push('scripts')
<script>
(function () {
    const body = document.getElementById('linesBody');
    const accountOptions = @json($accountOptions);
    let idx = 0;

    function rowHtml(i, line) {
        const desc = (line && line.description) ? line.description.replace(/"/g, '&quot;') : '';
        const amt  = (line && line.amount) ? line.amount : '';
        let opts = accountOptions;
        if (line && line.expense_account_id) {
            opts = opts.replace('value="' + line.expense_account_id + '"', 'value="' + line.expense_account_id + '" selected');
        }
        return `<tr>
            <td><input type="text" name="lines[${i}][description]" class="form-control form-control-sm" value="${desc}" required></td>
            <td><select name="lines[${i}][expense_account_id]" class="form-select form-select-sm" required>${opts}</select></td>
            <td><input type="number" step="0.01" min="0.01" name="lines[${i}][amount]" class="form-control form-control-sm text-end font-monospace line-amount" value="${amt}" required></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0 remove-line"><i class="bi bi-x-circle"></i></button></td>
        </tr>`;
    }

    function addRow(line) {
        body.insertAdjacentHTML('beforeend', rowHtml(idx++, line));
        recalc();
    }

    function recalc() {
        let sub = 0;
        document.querySelectorAll('.line-amount').forEach(el => sub += parseFloat(el.value || 0));
        const tax = parseFloat(document.getElementById('taxAmount').value || 0);
        document.getElementById('subtotalCell').textContent = sub.toFixed(2);
        document.getElementById('totalCell').textContent = (sub + tax).toFixed(2);
    }

    document.getElementById('addLine').addEventListener('click', () => addRow());
    document.getElementById('taxAmount').addEventListener('input', recalc);
    body.addEventListener('input', recalc);
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

    // Seed initial rows (old input on validation failure, else one empty row)
    const seed = @json(array_values($oldLines));
    if (seed.length) seed.forEach(addRow); else addRow();
})();
</script>
@endpush

@endsection
