@extends('layouts.app')

@section('title', 'New Receipt')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.receipts.index') }}">Receipts</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-receipt me-2 text-primary"></i>New Receipt</h4>
        <p class="text-muted mb-0 small">Record a customer payment receipt</p>
    </div>
    <a href="{{ route('finance.receipts.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card content-card" style="max-width: 750px;">
    <div class="card-body">
        <form method="POST" action="{{ route('finance.receipts.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Customer <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customerSelect" class="form-select form-select-sm s2-code @error('customer_id') is-invalid @enderror" data-s2-sel="name" required>
                        <option value="">— Select Customer —</option>
                        @foreach($customers as $c)
                        <option value="{{ $c->id }}"
                                data-code="{{ $c->code }}" data-name="{{ $c->name }}"
                                data-currency="{{ $c->currency }}"
                                {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                        @endforeach
                    </select>
                    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Receipt Date <span class="text-danger">*</span></label>
                    <input type="date" name="receipt_date" class="form-control form-control-sm @error('receipt_date') is-invalid @enderror"
                           value="{{ old('receipt_date', date('Y-m-d')) }}" required>
                    @error('receipt_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                <div class="col-md-4" id="chequeNoRow" style="{{ old('payment_method') === 'cheque' ? '' : 'display:none' }}">
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
                <div class="col-md-4">
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
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Exchange Rate <span class="text-danger">*</span></label>
                    <input type="number" name="exchange_rate" id="exchangeRateField" class="form-control form-control-sm @error('exchange_rate') is-invalid @enderror"
                           value="{{ old('exchange_rate', '1.000000') }}" required min="0.000001" step="0.000001">
                    <div class="form-text text-muted small">Base currency: {{ $baseCurrency }}. Auto-filled from the rate master; editable.</div>
                    @error('exchange_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Narration <span class="text-danger">*</span></label>
                    <input type="text" name="narration" class="form-control form-control-sm @error('narration') is-invalid @enderror"
                           value="{{ old('narration') }}" required maxlength="255" placeholder="e.g. Payment received from customer">
                    @error('narration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Create Receipt
                </button>
                <a href="{{ route('finance.receipts.index') }}" class="btn btn-outline-secondary btn-sm ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('paymentMethod').addEventListener('change', function () {
    document.getElementById('chequeNoRow').style.display = this.value === 'cheque' ? '' : 'none';
});

const baseCurrency = @json($baseCurrency);
const rateUrl      = @json(route('finance.fx-rate'));

// Auto-fill the exchange rate from the rate master when the currency (or date)
// changes. Base currency → rate locked at 1; foreign → fetched but editable.
function refreshFxRate() {
    const cur  = document.getElementById('currencyField').value;
    const rate = document.getElementById('exchangeRateField');
    const date = document.querySelector('[name="receipt_date"]');
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

// currencyField is a select2 (s2-code) — bind via jQuery so select2 changes fire.
$('#currencyField').on('change', refreshFxRate);
$('[name="receipt_date"]').on('change', refreshFxRate);
refreshFxRate(); // initial state

// customerSelect is a select2 (s2-code) — bind via jQuery so select2 changes fire.
// Customer change pre-fills their default currency.
$('#customerSelect').on('change', function () {
    var cur = this.options[this.selectedIndex]?.dataset.currency;
    if (cur) $('#currencyField').val(cur.toUpperCase()).trigger('change');
});
</script>
@endpush

@endsection
