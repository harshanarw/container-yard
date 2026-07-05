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
    @if($prefill ?? null)
    <input type="hidden" name="reference_supplier_invoice_id" value="{{ $prefill['supplier_invoice_id'] }}">
    <div class="alert alert-info py-2 small"><i class="bi bi-link-45deg me-1"></i>Raising a credit note against bill <strong>{{ $prefill['invoice_no'] }}</strong>. It will be applied to that bill automatically on approval.</div>
    @endif
    <div class="card content-card mb-3">
        <div class="card-header bg-transparent py-2"><strong class="small">Credit Note Details</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Supplier <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerSelect" class="form-select form-select-sm s2-code" data-s2-sel="name" required>
                        <option value="">— Select supplier —</option>
                        @foreach($suppliers as $c)
                        <option value="{{ $c->id }}" data-code="{{ $c->currency }}" data-name="{{ $c->name }}" {{ old('customer_id', $prefill['customer_id'] ?? '') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
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
                        <option value="{{ $cur->code }}" data-code="{{ $cur->code }}" data-name="{{ $cur->name }}" {{ old('currency', $prefill['currency'] ?? $baseCurrency) === $cur->code ? 'selected' : '' }}>{{ $cur->code }} — {{ $cur->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Exchange Rate <span class="text-danger">*</span></label>
                    <input type="number" name="exchange_rate" id="exchangeRateField" class="form-control form-control-sm" value="{{ old('exchange_rate', $prefill['exchange_rate'] ?? '1.000000') }}" required min="0.000001" step="0.000001">
                    <div class="form-text small">Base: {{ $baseCurrency }}</div>
                </div>
                <div class="col-md-9">
                    <label class="form-label fw-semibold small">Reason</label>
                    <input type="text" name="reason" class="form-control form-control-sm" value="{{ old('reason', isset($prefill) && $prefill ? 'Credit note for bill '.$prefill['invoice_no'] : '') }}" maxlength="255" placeholder="e.g. Returned goods / billing correction">
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
                    <tr>
                        <th>Description</th>
                        <th style="width:22%">Expense Account</th>
                        <th style="min-width:120px">Tax Code</th>
                        <th class="text-end" style="width:130px">Net Amount</th>
                        <th class="text-end" style="width:90px">SSCL</th>
                        <th class="text-end" style="width:90px">VAT</th>
                        <th style="width:36px"></th>
                    </tr>
                </thead>
                <tbody id="linesBody">
                @if($prefill ?? null)
                    @foreach($prefill['lines'] as $li => $pl)
                    <tr>
                        <td>
                            <input type="hidden" name="lines[{{ $li }}][charge_code_id]" value="{{ $pl['charge_code_id'] }}">
                            <input type="text" name="lines[{{ $li }}][description]" class="form-control form-control-sm" required maxlength="255" value="{{ $pl['description'] }}">
                        </td>
                        <td>
                            <select name="lines[{{ $li }}][expense_account_id]" class="form-select form-select-sm s2-code" data-s2-sel="name">
                                <option value="">— Default expense —</option>
                                @foreach($expenseAccounts as $a)
                                <option value="{{ $a->id }}" data-code="{{ $a->code }}" data-name="{{ $a->name }}" {{ ($pl['expense_account_id'] ?? null) == $a->id ? 'selected' : '' }}>{{ $a->code }} — {{ $a->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="lines[{{ $li }}][tax_code_id]" class="form-select form-select-sm tc-select">
                                <option value="" data-t1="0" data-t2="0">— No tax —</option>
                                @foreach($taxCodes as $tc)
                                <option value="{{ $tc->id }}" data-t1="{{ $tc->tax1_rate }}" data-t2="{{ $tc->tax2_rate }}" {{ ($pl['tax_code_id'] ?? null) == $tc->id ? 'selected' : '' }}>{{ $tc->code }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="lines[{{ $li }}][tax1_rate]" class="tax1-rate" value="{{ $pl['tax1_rate'] }}">
                            <input type="hidden" name="lines[{{ $li }}][tax2_rate]" class="tax2-rate" value="{{ $pl['tax2_rate'] }}">
                        </td>
                        <td><input type="number" name="lines[{{ $li }}][amount]" class="form-control form-control-sm text-end font-monospace line-amt" min="0.01" step="0.01" required value="{{ number_format($pl['amount'], 2, '.', '') }}"></td>
                        <td class="text-end font-monospace text-muted line-sscl">0.00</td>
                        <td class="text-end font-monospace text-muted line-vat">0.00</td>
                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rm-line"><i class="bi bi-x"></i></button></td>
                    </tr>
                    @endforeach
                @endif
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="3" class="text-end small text-muted">Subtotal (net)</td>
                        <td class="text-end font-monospace fw-semibold" id="subtotalDisp">0.00</td>
                        <td class="text-end font-monospace text-muted" id="ssclDisp">0.00</td>
                        <td class="text-end font-monospace text-muted" id="vatDisp">0.00</td>
                        <td></td>
                    </tr>
                    <tr class="fw-bold">
                        <td colspan="5" class="text-end">Total credited</td>
                        <td class="text-end font-monospace" id="totalDisp">0.00</td>
                        <td><span id="ccyDisp" class="small text-muted fw-normal">{{ $baseCurrency }}</span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer bg-transparent text-end">
            <a href="{{ route('finance.ap-credit-notes.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Draft</button>
        </div>
    </div>
</form>

<template id="lineTpl">
    <tr>
        <td><input type="text" name="lines[IDX][description]" class="form-control form-control-sm" required maxlength="255"></td>
        <td>
            <select name="lines[IDX][expense_account_id]" class="form-select form-select-sm s2-code" data-s2-sel="name">
                <option value="">— Default expense —</option>
                @foreach($expenseAccounts as $a)
                <option value="{{ $a->id }}" data-code="{{ $a->code }}" data-name="{{ $a->name }}">{{ $a->code }} — {{ $a->name }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="lines[IDX][tax_code_id]" class="form-select form-select-sm tc-select">
                <option value="" data-t1="0" data-t2="0">— No tax —</option>
                @foreach($taxCodes as $tc)
                <option value="{{ $tc->id }}" data-t1="{{ $tc->tax1_rate }}" data-t2="{{ $tc->tax2_rate }}">{{ $tc->code }}</option>
                @endforeach
            </select>
            <input type="hidden" name="lines[IDX][tax1_rate]" class="tax1-rate" value="0">
            <input type="hidden" name="lines[IDX][tax2_rate]" class="tax2-rate" value="0">
        </td>
        <td><input type="number" name="lines[IDX][amount]" class="form-control form-control-sm text-end font-monospace line-amt" min="0.01" step="0.01" required></td>
        <td class="text-end font-monospace text-muted line-sscl">0.00</td>
        <td class="text-end font-monospace text-muted line-vat">0.00</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rm-line"><i class="bi bi-x"></i></button></td>
    </tr>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const body = document.getElementById('linesBody');
    const tpl  = document.getElementById('lineTpl').innerHTML;
    const prefilled = {{ ($prefill ?? null) ? 'true' : 'false' }};
    let idx = {{ ($prefill ?? null) ? count($prefill['lines']) : 0 }};

    // SSCL on net, VAT on (net + SSCL) — identical to the supplier invoice.
    function recomputeRow(row) {
        const net  = parseFloat(row.querySelector('.line-amt')?.value || 0) || 0;
        const t1   = parseFloat(row.querySelector('.tax1-rate')?.value || 0) || 0;
        const t2   = parseFloat(row.querySelector('.tax2-rate')?.value || 0) || 0;
        const sscl = Math.round(net * t1) / 100;
        const vat  = Math.round((net + sscl) * t2) / 100;
        const sc = row.querySelector('.line-sscl'); if (sc) sc.textContent = sscl.toFixed(2);
        const vc = row.querySelector('.line-vat');  if (vc) vc.textContent = vat.toFixed(2);
        return { net, sscl, vat };
    }
    function recompute() {
        let sN = 0, sS = 0, sV = 0;
        body.querySelectorAll('tr').forEach(row => { const r = recomputeRow(row); sN += r.net; sS += r.sscl; sV += r.vat; });
        document.getElementById('subtotalDisp').textContent = sN.toFixed(2);
        document.getElementById('ssclDisp').textContent = sS.toFixed(2);
        document.getElementById('vatDisp').textContent = sV.toFixed(2);
        document.getElementById('totalDisp').textContent = (sN + sS + sV).toFixed(2);
    }
    function addLine() {
        body.insertAdjacentHTML('beforeend', tpl.replace(/IDX/g, idx++));
        // Rows added after page load need Select2 initialised here (the global
        // s2-code auto-init only runs once, on DOMContentLoaded). The very first
        // addLine() runs during page parse — before initS2Code is defined — so
        // guard it; that initial row is covered by the global auto-init instead.
        if (typeof window.initS2Code === 'function') {
            window.initS2Code($(body.lastElementChild).find('select.s2-code'), { width: '100%' });
        }
        recompute();
    }
    document.getElementById('addLine').addEventListener('click', addLine);
    body.addEventListener('input', e => { if (e.target.classList.contains('line-amt')) recompute(); });
    // Tax-code change → copy its SSCL/VAT rates onto the row, then recompute.
    body.addEventListener('change', e => {
        if (e.target.classList.contains('tc-select')) {
            const opt = e.target.options[e.target.selectedIndex];
            const row = e.target.closest('tr');
            row.querySelector('.tax1-rate').value = opt ? (opt.dataset.t1 || 0) : 0;
            row.querySelector('.tax2-rate').value = opt ? (opt.dataset.t2 || 0) : 0;
            recompute();
        }
    });
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
    // customerSelect is a select2 (s2-code) — bind via jQuery so select2 changes fire.
    $('#customerSelect').on('change', function () {
        const cur = this.options[this.selectedIndex]?.dataset.code;
        if (cur) $('#currencyField').val(cur.toUpperCase()).trigger('change');
    });
    if (prefilled) {
        document.getElementById('ccyDisp').textContent = document.getElementById('currencyField').value;
        recompute();
    } else {
        addLine();
        refreshRate();
    }
})();
</script>
@endpush
