@extends('layouts.app')

@section('title', 'New AP Credit Note')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.ap-credit-notes.index') }}">AP Credit Notes</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div><h4><i class="bi bi-arrow-clockwise me-2 text-danger"></i>New AP Credit Note</h4>
        <p class="text-muted mb-0 small">Record a vendor credit note that reduces what you owe</p></div>
    <a href="{{ route('finance.ap-credit-notes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('finance.ap-credit-notes.store') }}">
    @csrf
    <div class="card content-card mb-3">
        <div class="card-header bg-transparent py-2"><strong class="small">Credit Note Details</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Supplier <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerSelect" class="form-select form-select-sm s2-code" data-s2-sel="name" required>
                        <option value="">— Select supplier —</option>
                        @foreach($suppliers as $c)
                        <option value="{{ $c->id }}" data-code="{{ $c->currency }}" data-name="{{ $c->name }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Vendor's Credit No</label>
                    <input type="text" name="supplier_credit_no" class="form-control form-control-sm" value="{{ old('supplier_credit_no') }}" maxlength="50">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
                    <input type="date" name="credit_date" class="form-control form-control-sm" value="{{ old('credit_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Currency <span class="text-danger">*</span></label>
                    <select name="currency" id="currencyField" class="form-select form-select-sm s2-code" data-s2-sel="name" required>
                        @foreach($currencies as $cur)
                        <option value="{{ $cur->code }}" data-code="{{ $cur->code }}" data-name="{{ $cur->name }}" {{ old('currency', $baseCurrency) === $cur->code ? 'selected' : '' }}>{{ $cur->code }} — {{ $cur->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Exchange Rate <span class="text-danger">*</span></label>
                    <input type="number" name="exchange_rate" id="exchangeRateField" class="form-control form-control-sm" value="{{ old('exchange_rate','1.000000') }}" required min="0.000001" step="0.000001">
                    <div class="form-text small">Base: {{ $baseCurrency }}</div>
                </div>
                <div class="col-md-9">
                    <label class="form-label fw-semibold small">Reason</label>
                    <input type="text" name="reason" class="form-control form-control-sm" value="{{ old('reason') }}" maxlength="255" placeholder="e.g. Returned goods / billing correction">
                </div>
            </div>
        </div>
    </div>

    <div class="card content-card">
        <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Lines</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add line</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th>Description</th><th style="width:30%">Expense Account</th><th class="text-end" style="width:160px">Amount</th><th style="width:36px"></th></tr>
                </thead>
                <tbody id="linesBody"></tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent">
            <div class="row g-2 justify-content-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Input VAT to reverse</label>
                    <input type="number" name="tax_amount" id="taxAmount" class="form-control form-control-sm text-end font-monospace" value="{{ old('tax_amount', '0.00') }}" min="0" step="0.01">
                </div>
                <div class="col-md-3 text-end">
                    <div class="small text-muted mt-4">Subtotal: <span class="fw-semibold font-monospace" id="subtotalDisp">0.00</span></div>
                    <div class="fw-bold">Total: <span class="font-monospace" id="totalDisp">0.00</span> <span id="ccyDisp">{{ $baseCurrency }}</span></div>
                </div>
            </div>
            <div class="mt-3 text-end">
                <a href="{{ route('finance.ap-credit-notes.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Draft</button>
            </div>
        </div>
    </div>
</form>

<template id="lineTpl">
    <tr>
        <td><input type="text" name="lines[IDX][description]" class="form-control form-control-sm" required maxlength="255"></td>
        <td>
            <select name="lines[IDX][expense_account_id]" class="form-select form-select-sm">
                <option value="">— Default expense —</option>
                @foreach($expenseAccounts as $a)
                <option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="lines[IDX][amount]" class="form-control form-control-sm text-end font-monospace line-amt" min="0.01" step="0.01" required></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rm-line"><i class="bi bi-x"></i></button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.getElementById('linesBody');
    const tpl  = document.getElementById('lineTpl').innerHTML;
    let idx = 0;
    function addLine() { body.insertAdjacentHTML('beforeend', tpl.replace(/IDX/g, idx++)); recompute(); }
    function recompute() {
        let sub = 0;
        document.querySelectorAll('.line-amt').forEach(i => sub += parseFloat(i.value || 0) || 0);
        const tax = parseFloat(document.getElementById('taxAmount').value || 0) || 0;
        document.getElementById('subtotalDisp').textContent = sub.toFixed(2);
        document.getElementById('totalDisp').textContent = (sub + tax).toFixed(2);
    }
    document.getElementById('addLine').addEventListener('click', addLine);
    document.getElementById('taxAmount').addEventListener('input', recompute);
    body.addEventListener('input', e => { if (e.target.classList.contains('line-amt')) recompute(); });
    body.addEventListener('click', e => { if (e.target.closest('.rm-line')) { e.target.closest('tr').remove(); recompute(); } });

    const baseCurrency = @json($baseCurrency);
    const rateUrl = @json(route('finance.fx-rate'));
    function refreshRate() {
        const cur = document.getElementById('currencyField').value;
        document.getElementById('ccyDisp').textContent = cur;
        const rateEl = document.getElementById('exchangeRateField');
        if (cur === baseCurrency) { rateEl.value = '1.000000'; rateEl.readOnly = true; return; }
        rateEl.readOnly = false;
        fetch(rateUrl + '?from=' + encodeURIComponent(cur), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(d => { if (d && d.rate) rateEl.value = Number(d.rate).toFixed(6); }).catch(() => {});
    }
    $('#currencyField').on('change', refreshRate);
    document.getElementById('customerSelect').addEventListener('change', function () {
        const cur = this.options[this.selectedIndex]?.dataset.code;
        if (cur) $('#currencyField').val(cur.toUpperCase()).trigger('change');
    });
    addLine(); refreshRate();
})();
</script>
@endpush
