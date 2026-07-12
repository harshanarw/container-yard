@extends('layouts.app')

@section('title', 'New Payment Voucher')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.vouchers.index') }}">Payment Vouchers</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-cash-coin me-2 text-primary"></i>New Payment Voucher</h4>
        <p class="text-muted mb-0 small">Record an expense payment voucher</p>
    </div>
    <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card content-card" style="max-width: 750px;">
    <div class="card-body">
        <form method="POST" action="{{ route('finance.vouchers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Supplier / Contact</label>
                    <select name="customer_id" id="supplierSelect" class="form-select form-select-sm s2-code @error('customer_id') is-invalid @enderror" data-s2-sel="name">
                        <option value="">— None / one-off payee —</option>
                        @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" data-code="{{ $sup->code }}" data-name="{{ $sup->name }}" {{ old('customer_id') == $sup->id ? 'selected' : '' }}>
                            {{ $sup->code }} — {{ $sup->name }}
                        </option>
                        @endforeach
                    </select>
                    <div class="form-text small">Link to a contact to allocate this payment against their supplier invoices.</div>
                    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Payee Name <span class="text-danger">*</span></label>
                    <input type="text" name="payee_name" id="payeeName" class="form-control form-control-sm @error('payee_name') is-invalid @enderror"
                           value="{{ old('payee_name') }}" required maxlength="150" placeholder="Supplier / Vendor name">
                    @error('payee_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Voucher Date <span class="text-danger">*</span></label>
                    <input type="date" name="voucher_date" class="form-control form-control-sm @error('voucher_date') is-invalid @enderror"
                           value="{{ old('voucher_date', date('Y-m-d')) }}" required>
                    @error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Bank Account</label>
                    <select name="bank_account_id" class="form-select form-select-sm select2 @error('bank_account_id') is-invalid @enderror">
                        <option value="">— None / Cash —</option>
                        @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->id }}" {{ old('bank_account_id') == $ba->id ? 'selected' : '' }}>
                            {{ $ba->account_name }} ({{ $ba->bank_name }}) — {{ $ba->currency }}
                        </option>
                        @endforeach
                    </select>
                    @error('bank_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Expense Account</label>
                    <select name="expense_account_id" class="form-select form-select-sm select2 @error('expense_account_id') is-invalid @enderror">
                        <option value="">— Select Expense Account —</option>
                        @php $lastClass = null; @endphp
                        @foreach($expenseAccounts as $acc)
                        @if($acc->classification !== $lastClass)
                        @if($lastClass !== null)</optgroup>@endif
                        <optgroup label="{{ ucfirst($acc->classification) }}">
                        @php $lastClass = $acc->classification; @endphp
                        @endif
                        <option value="{{ $acc->id }}" {{ old('expense_account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->code }} — {{ $acc->name }}
                        </option>
                        @endforeach
                        @if($lastClass !== null)</optgroup>@endif
                    </select>
                    <div class="form-text small" id="expenseAccountHint">Ignored when a supplier is selected — the payment debits Accounts Payable instead.</div>
                    @error('expense_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Job <span class="text-muted">(costing)</span></label>
                    <select name="yard_job_id" class="form-select form-select-sm select2">
                        <option value="">— None —</option>
                        @foreach($jobs as $j)
                            <option value="{{ $j->id }}" {{ old('yard_job_id') == $j->id ? 'selected' : '' }}>{{ $j->job_no }} · {{ $j->job_type_code }} · {{ $j->customer?->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text small">Attributes a direct expense to a container job for job P&amp;L.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Payment Method <span class="text-danger">*</span></label>
                    <select name="payment_method" id="paymentMethod" class="form-select form-select-sm @error('payment_method') is-invalid @enderror" required>
                        <option value="">— Select —</option>
                        <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Cash</option>
                        <option value="cheque" {{ old('payment_method') === 'cheque' ? 'selected' : '' }}>Cheque</option>
                        <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                        <option value="online" {{ old('payment_method') === 'online' ? 'selected' : '' }}>Online</option>
                    </select>
                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6" id="chequeNoRow" style="{{ old('payment_method') === 'cheque' ? '' : 'display:none' }}">
                    <label class="form-label fw-semibold small">Cheque No</label>
                    <input type="text" name="cheque_no" class="form-control form-control-sm @error('cheque_no') is-invalid @enderror"
                           value="{{ old('cheque_no') }}" maxlength="50">
                    @error('cheque_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Reference No</label>
                    <input type="text" name="reference_no" class="form-control form-control-sm @error('reference_no') is-invalid @enderror"
                           value="{{ old('reference_no') }}" maxlength="100">
                    @error('reference_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Amount <span class="text-danger">*</span></label>
                    <input type="number" name="amount" class="form-control form-control-sm @error('amount') is-invalid @enderror"
                           value="{{ old('amount') }}" required min="0.0001" step="0.0001">
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Currency <span class="text-danger">*</span></label>
                    <select name="currency" id="currencyField" class="form-select form-select-sm s2-code @error('currency') is-invalid @enderror" data-s2-sel="name" required>
                        @foreach($currencies as $cur)
                        <option value="{{ $cur->code }}"
                            data-code="{{ $cur->code }}" data-name="{{ $cur->name }}"
                            {{ old('currency', $baseCurrency) === $cur->code ? 'selected' : '' }}>
                            {{ $cur->code }} — {{ $cur->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Exchange Rate <span class="text-danger">*</span></label>
                    <input type="number" name="exchange_rate" id="exchangeRateField" class="form-control form-control-sm @error('exchange_rate') is-invalid @enderror"
                           value="{{ old('exchange_rate', '1.000000') }}" required min="0.000001" step="0.000001">
                    <div class="form-text text-muted small">Base currency: {{ $baseCurrency }}. Auto-filled from the rate master; editable.</div>
                    @error('exchange_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Narration <span class="text-danger">*</span></label>
                    <input type="text" name="narration" class="form-control form-control-sm @error('narration') is-invalid @enderror"
                           value="{{ old('narration') }}" required maxlength="255" placeholder="e.g. Payment to supplier for goods">
                    @error('narration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Create Voucher
                </button>
                <a href="{{ route('finance.vouchers.index') }}" class="btn btn-outline-secondary btn-sm ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('paymentMethod').addEventListener('change', function () {
    document.getElementById('chequeNoRow').style.display = this.value === 'cheque' ? '' : 'none';
});

// supplierSelect is a select2 (s2-code) — bind via jQuery so select2 changes fire.
// Auto-fill payee name from the selected supplier (only if payee is empty).
$('#supplierSelect').on('change', function () {
    const name = this.options[this.selectedIndex]?.dataset.name || '';
    const payee = document.getElementById('payeeName');
    if (name && !payee.value.trim()) payee.value = name;
});

const baseCurrency = @json($baseCurrency);
const rateUrl      = @json(route('finance.fx-rate'));

// Auto-fill the exchange rate from the rate master when the currency (or date)
// changes. Base currency → rate locked at 1; foreign → fetched but editable.
function refreshFxRate() {
    const cur  = document.getElementById('currencyField').value;
    const rate = document.getElementById('exchangeRateField');
    const date = document.querySelector('[name="voucher_date"]');
    if (!cur || !rate) return;

    if (cur === baseCurrency) {
        rate.value = '1.000000';
        rate.readOnly = true;
        return;
    }
    rate.readOnly = false;
    fetch(rateUrl + '?from=' + encodeURIComponent(cur) + '&date=' + encodeURIComponent(date ? date.value : ''),
          { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => { if (d && d.rate) rate.value = Number(d.rate).toFixed(6); })
        .catch(() => {});
}

$('#currencyField').on('change', refreshFxRate);
$('[name="voucher_date"]').on('change', refreshFxRate);
refreshFxRate(); // initial state
</script>
@endpush

@endsection
