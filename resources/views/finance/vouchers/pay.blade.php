@extends('layouts.app')

@section('title', 'Pay Bills')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.vouchers.index') }}">Payment Vouchers</a></li>
    <li class="breadcrumb-item active">Pay Bills</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-cash-stack me-2 text-danger"></i>Pay Bills</h4>
        <p class="text-muted mb-0 small">Select a supplier, tick the bills being paid, then save or post the voucher.</p>
    </div>
    <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Step 1 — choose the supplier (GET reload) --}}
<div class="card content-card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('finance.vouchers.pay') }}" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-semibold small">Supplier / Contact <span class="text-danger">*</span></label>
                <select name="customer_id" class="form-select form-select-sm s2-code" data-s2-sel="name"
                        onchange="this.form.submit()">
                    <option value="">— Select supplier —</option>
                    @foreach($suppliers as $s)
                    <option value="{{ $s->id }}"
                        data-code="{{ $s->currency }}" data-name="{{ $s->name }}"
                        {{ (string) optional($supplier)->id === (string) $s->id ? 'selected' : '' }}>
                        {{ $s->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Load bills</button>
            </div>
        </form>
    </div>
</div>

@if($supplier)
    @if($pendingInvoices->isEmpty())
    <div class="card content-card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2 opacity-50"></i>
            No outstanding (posted) bills for <strong>{{ $supplier->name }}</strong>.
        </div>
    </div>
    @else
    <form method="POST" action="{{ route('finance.vouchers.pay.store') }}" id="payForm">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $supplier->id }}">

        <div class="card content-card mb-3">
            <div class="card-header bg-transparent py-2"><strong class="small">Voucher Details — {{ $supplier->name }}</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Voucher Date <span class="text-danger">*</span></label>
                        <input type="date" name="voucher_date" class="form-control form-control-sm" value="{{ old('voucher_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Bank Account</label>
                        <select name="bank_account_id" class="form-select form-select-sm select2">
                            <option value="">— Select —</option>
                            @foreach($bankAccounts as $ba)
                            <option value="{{ $ba->id }}" {{ old('bank_account_id') == $ba->id ? 'selected' : '' }}>{{ $ba->display_name ?? ($ba->bank_name.' — '.$ba->account_name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="paymentMethod" class="form-select form-select-sm" required>
                            @foreach(['bank_transfer'=>'Bank Transfer','cash'=>'Cash','cheque'=>'Cheque','online'=>'Online'] as $val=>$lbl)
                            <option value="{{ $val }}" {{ old('payment_method','bank_transfer')===$val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3" id="chequeNoRow" style="{{ old('payment_method')==='cheque' ? '' : 'display:none' }}">
                        <label class="form-label fw-semibold small">Cheque No</label>
                        <input type="text" name="cheque_no" class="form-control form-control-sm" value="{{ old('cheque_no') }}" maxlength="50">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Currency <span class="text-danger">*</span></label>
                        <select name="currency" id="currencyField" class="form-select form-select-sm s2-code" data-s2-sel="name" required>
                            @foreach($currencies as $cur)
                            <option value="{{ $cur->code }}" data-code="{{ $cur->code }}" data-name="{{ $cur->name }}"
                                {{ old('currency', $supplier->currency ?: $baseCurrency) === $cur->code ? 'selected' : '' }}>
                                {{ $cur->code }} — {{ $cur->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Exchange Rate <span class="text-danger">*</span></label>
                        <input type="number" name="exchange_rate" id="exchangeRateField" class="form-control form-control-sm" value="{{ old('exchange_rate','1.000000') }}" required min="0.000001" step="0.000001">
                        <div class="form-text text-muted small">Base: {{ $baseCurrency }}. Auto-filled; editable.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Reference No</label>
                        <input type="text" name="reference_no" class="form-control form-control-sm" value="{{ old('reference_no') }}" maxlength="100">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Narration <span class="text-danger">*</span></label>
                        <input type="text" name="narration" class="form-control form-control-sm" value="{{ old('narration', 'Payment to '.$supplier->name) }}" required maxlength="255">
                    </div>
                </div>
            </div>
        </div>

        <div class="card content-card">
            <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
                <strong class="small">Outstanding Bills ({{ $pendingInvoices->count() }})</strong>
                <span class="small">Total to pay: <span class="fw-bold font-monospace" id="totalDisplay">0.00</span> <span id="ccyLabel">{{ $baseCurrency }}</span></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px;"><input type="checkbox" id="checkAll" class="form-check-input"></th>
                            <th>Bill No</th>
                            <th>Reference</th>
                            <th>Due</th>
                            <th class="text-center">Ccy</th>
                            <th class="text-end">Outstanding</th>
                            <th class="text-end" style="width:160px;">Amount to Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingInvoices as $i => $pi)
                        @php $rowCcy = $pi['currency'] ?: $baseCurrency; @endphp
                        <tr data-currency="{{ $rowCcy }}">
                            <td>
                                <input type="checkbox" class="form-check-input row-check" data-i="{{ $i }}"
                                       name="allocations[{{ $i }}][selected]" value="1">
                            </td>
                            <td class="font-monospace">{{ $pi['invoice_no'] }}</td>
                            <td class="text-muted font-monospace small">{{ $pi['reference'] ?: '—' }}</td>
                            <td class="{{ $pi['past_due'] ? 'text-danger fw-semibold' : 'text-muted' }}">
                                {{ $pi['due_date'] ? $pi['due_date']->format('d M Y') : '—' }}
                            </td>
                            <td class="text-center"><span class="badge bg-light text-dark">{{ $rowCcy }}</span></td>
                            <td class="text-end font-monospace">{{ number_format($pi['outstanding'], 2) }}</td>
                            <td>
                                <input type="hidden" name="allocations[{{ $i }}][id]" value="{{ $pi['id'] }}">
                                <input type="number" step="0.01" min="0"
                                       class="form-control form-control-sm text-end font-monospace amount-input"
                                       data-i="{{ $i }}" data-outstanding="{{ $pi['outstanding'] }}"
                                       name="allocations[{{ $i }}][amount]"
                                       value="{{ number_format($pi['outstanding'], 2, '.', '') }}" disabled>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" name="action" value="draft" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-save me-1"></i>Save Draft
                </button>
                @if($canPost)
                <button type="submit" name="action" value="post" class="btn btn-primary btn-sm">
                    <i class="bi bi-check2-circle me-1"></i>Save &amp; Post
                </button>
                @endif
            </div>
        </div>
    </form>
    @endif
@endif

@endsection

@push('scripts')
<script>
(function () {
    const payMethod = document.getElementById('paymentMethod');
    if (payMethod) payMethod.addEventListener('change', function () {
        const row = document.getElementById('chequeNoRow');
        if (row) row.style.display = this.value === 'cheque' ? '' : 'none';
    });

    const baseCurrency = @json($baseCurrency);
    const rateUrl      = @json(route('finance.fx-rate'));
    const currencyEl   = document.getElementById('currencyField');
    const rateEl       = document.getElementById('exchangeRateField');
    const ccyLabel     = document.getElementById('ccyLabel');
    const totalDisplay = document.getElementById('totalDisplay');

    function selectedCurrency() { return currencyEl ? currencyEl.value : baseCurrency; }

    function recomputeTotal() {
        let total = 0;
        document.querySelectorAll('.row-check').forEach(cb => {
            if (cb.checked) {
                const amt = document.querySelector('.amount-input[data-i="' + cb.dataset.i + '"]');
                total += parseFloat(amt && amt.value ? amt.value : 0) || 0;
            }
        });
        if (totalDisplay) totalDisplay.textContent = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function applyCurrencyFilter() {
        const cur = selectedCurrency();
        if (ccyLabel) ccyLabel.textContent = cur;
        document.querySelectorAll('tr[data-currency]').forEach(tr => {
            const match = (tr.dataset.currency || baseCurrency) === cur;
            const cb  = tr.querySelector('.row-check');
            const amt = tr.querySelector('.amount-input');
            tr.style.opacity = match ? '' : '0.45';
            if (cb) { cb.disabled = !match; if (!match) cb.checked = false; }
            if (amt && (!match || (cb && !cb.checked))) amt.disabled = true;
        });
        recomputeTotal();
    }

    document.querySelectorAll('.row-check').forEach(cb => {
        cb.addEventListener('change', function () {
            const amt = document.querySelector('.amount-input[data-i="' + this.dataset.i + '"]');
            if (amt) { amt.disabled = !this.checked; if (this.checked && !amt.value) amt.value = amt.dataset.outstanding; }
            recomputeTotal();
        });
    });
    document.querySelectorAll('.amount-input').forEach(a => a.addEventListener('input', recomputeTotal));

    const checkAll = document.getElementById('checkAll');
    if (checkAll) checkAll.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => {
            if (!cb.disabled) { cb.checked = this.checked; cb.dispatchEvent(new Event('change')); }
        });
    });

    function refreshFxRate() {
        const cur = selectedCurrency();
        const date = document.querySelector('[name="voucher_date"]');
        if (!cur || !rateEl) return;
        if (cur === baseCurrency) { rateEl.value = '1.000000'; rateEl.readOnly = true; return; }
        rateEl.readOnly = false;
        fetch(rateUrl + '?from=' + encodeURIComponent(cur) + '&date=' + encodeURIComponent(date ? date.value : ''),
              { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json()).then(d => { if (d && d.rate) rateEl.value = Number(d.rate).toFixed(6); }).catch(() => {});
    }

    if (currencyEl) {
        $('#currencyField').on('change', function () { applyCurrencyFilter(); refreshFxRate(); });
        $('[name="voucher_date"]').on('change', refreshFxRate);
        applyCurrencyFilter();
        refreshFxRate();
    }
})();
</script>
@endpush
