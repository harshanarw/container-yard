@extends('layouts.app')
@section('title', 'New Reefer Electricity Invoice')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('billing.reefer.index') }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-lightning-charge-fill text-primary me-2"></i>New Reefer Electricity Invoice</h4>
        <p class="text-muted small mb-0">Select customer and period, preview charges, then create invoice.</p>
    </div>
</div>


<form id="billingForm" action="{{ route('billing.reefer.store') }}" method="POST">
    @csrf
    <div class="row g-4">
        {{-- Left: parameters --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-transparent fw-semibold">Billing Parameters</div>
                <div class="card-body">
                    @php $todayRate = \App\Models\ExchangeRate::getRate('USD', 'LKR', date('Y-m-d')); @endphp

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customerId" class="form-select s2-code" required data-s2-sel="name">
                            <option value="">— Select Customer —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}"
                                        data-code="{{ $c->code }}" data-name="{{ $c->name }}"
                                        data-tax-exempt="{{ $c->tax_exempt ? '1' : '0' }}"
                                        data-billing-party-id="{{ $c->billing_party_id ?? '' }}"
                                        data-billing-party-name="{{ $c->billingParty->name ?? '' }}"
                                        data-billing-party-address="{{ $c->billingParty->address ?? '' }}"
                                        {{ old('customer_id') == $c->id ? 'selected' : '' }}>
                                    [{{ $c->code }}] {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Billing Party</label>
                        <select name="billing_party_id" id="billingPartyId" class="form-select s2-code" data-s2-sel="name">
                            <option value="">— Select Billing Party —</option>
                            @foreach($allCustomers as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" data-address="{{ $c->address ?? '' }}">
                                    [{{ $c->code }}] {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="billingPartyInfo" class="px-2 py-1 rounded small mt-1 d-none" style="border-left:3px solid #0d6efd;background:#f8f9ff;">
                            <span class="text-muted" id="billingPartyAddress" style="font-size:.78rem;"></span>
                        </div>
                    </div>

                    <div id="taxExemptAlert" class="alert alert-warning py-2 small d-none mb-2">
                        <i class="bi bi-shield-check me-1"></i>
                        <strong>Tax Exempt Customer</strong> — all tax rates will be applied as 0%.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bill Type <span class="text-danger">*</span></label>
                        <select name="service_type" id="serviceType" class="form-select" required>
                            <option value="long_term" {{ old('service_type', 'long_term') === 'long_term' ? 'selected' : '' }}>Long-Term Electricity (daily)</option>
                            <option value="pti" {{ old('service_type') === 'pti' ? 'selected' : '' }}>Short-Term PTI (hourly)</option>
                        </select>
                        <div class="form-text">PTI is billed hourly (usually USD); Long-Term electricity daily (usually LKR). One invoice per type.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Invoice Type <span class="text-danger">*</span></label>
                        <select name="invoice_type" id="invoiceType" class="form-select" required>
                            <option value="tax_invoice" {{ old('invoice_type') === 'tax_invoice' ? 'selected' : '' }}>Tax Invoice</option>
                            <option value="invoice" {{ old('invoice_type', 'invoice') === 'invoice' ? 'selected' : '' }}>Invoice</option>
                            <option value="debit_note" {{ old('invoice_type') === 'debit_note' ? 'selected' : '' }}>Debit Note</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" id="invoiceDate" class="form-control" required value="{{ old('invoice_date', date('Y-m-d')) }}">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="form-label fw-semibold">Invoice Currency <span class="text-danger">*</span></label>
                            <select name="invoice_currency" id="invoiceCurrency" class="form-select">
                                <option value="LKR">LKR — Sri Lankan Rupee</option>
                                <option value="USD">USD — US Dollar</option>
                                <option value="EUR">EUR — Euro</option>
                                <option value="GBP">GBP — British Pound</option>
                                <option value="SGD">SGD — Singapore Dollar</option>
                                <option value="AUD">AUD — Australian Dollar</option>
                            </select>
                            <div class="form-text">Values always stored in LKR</div>
                        </div>
                        <div class="col-7">
                            <label class="form-label fw-semibold"><span id="rateLabel">USD → LKR Rate</span> <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text small" id="ratePrefixLabel">1 USD =</span>
                                <input type="number" name="exchange_rate" id="exchangeRate" class="form-control"
                                       value="{{ $todayRate ? number_format((float)$todayRate, 4, '.', '') : old('exchange_rate', $exchangeRate) }}"
                                       min="0.0001" step="0.0001">
                                <span class="input-group-text" id="rateSuffixLabel">LKR</span>
                            </div>
                            <div class="form-text d-flex align-items-center gap-1">
                                <span id="rateNote" class="text-muted"><i class="bi bi-info-circle me-1"></i>Auto-loaded from the daily exchange rate</span>
                                <span id="rateSpinner" class="spinner-border spinner-border-sm d-none" style="width:.75rem;height:.75rem;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Period From <span class="text-danger">*</span></label>
                            <input type="date" name="period_from" id="periodFrom" class="form-control" required value="{{ old('period_from', date('Y-m-01')) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Period To <span class="text-danger">*</span></label>
                            <input type="date" name="period_to" id="periodTo" class="form-control" required value="{{ old('period_to', date('Y-m-d')) }}">
                        </div>
                    </div>

                    {{-- SSCL/VAT fallback — normally auto-derived from the bill type's charge code --}}
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">SSCL % <small class="text-muted">(fallback)</small></label>
                            <input type="number" name="sscl_pct" id="ssclPct" class="form-control" value="{{ old('sscl_pct', 0) }}" step="0.01" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label">VAT % <small class="text-muted">(fallback)</small></label>
                            <input type="number" name="vat_pct" id="vatPct" class="form-control" value="{{ old('vat_pct', 0) }}" step="0.01" min="0" max="100">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>

                    <button type="button" id="previewBtn" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Preview Charges
                    </button>
                </div>
            </div>
        </div>

        {{-- Right: preview --}}
        <div class="col-lg-8">
            <div class="card shadow-sm" id="previewCard" style="display:none">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Charge Preview</span>
                    <span id="previewSkipped" class="badge bg-warning-subtle text-warning border" style="display:none"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="previewTable">
                        <thead class="table-light">
                            <tr>
                                <th>Container</th>
                                <th>Plug-In</th>
                                <th>Plug-Out</th>
                                <th>Mode</th>
                                <th>Chargeable</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Total</th>
                                <th class="text-end text-muted">Value (LKR)</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="row justify-content-end g-1 small">
                        <div class="col-md-5">
                            <div class="d-flex justify-content-between text-muted"><span>Subtotal</span><span id="sumSubtotal">—</span></div>
                            <div class="d-flex justify-content-between text-muted"><span>SSCL</span><span id="sumSscl">—</span></div>
                            <div class="d-flex justify-content-between text-muted"><span>VAT</span><span id="sumVat">—</span></div>
                            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>Total</span><span id="sumTotal">—</span></div>
                            <div class="d-flex justify-content-between text-muted small mt-1"><span>Total Value (LKR)</span><span id="sumValue">—</span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="previewEmpty" class="alert alert-warning mt-3" style="display:none">
                No completed reefer sessions found for the selected customer and period.
            </div>

            <div id="missingRatesPanel" class="d-none mt-3"></div>

            <div class="mt-3 text-end" id="createBtnWrap" style="display:none">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="bi bi-plus-lg me-1"></i>Create Invoice
                </button>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const previewBtn   = document.getElementById('previewBtn');
    const previewCard  = document.getElementById('previewCard');
    const previewEmpty = document.getElementById('previewEmpty');
    const createWrap   = document.getElementById('createBtnWrap');
    const previewBody  = document.getElementById('previewBody');
    const form         = document.getElementById('billingForm');
    let previewMissing = [];

    const customerSel = document.getElementById('customerId');
    const billingSel  = document.getElementById('billingPartyId');
    const serviceSel  = document.getElementById('serviceType');
    const currencySel = document.getElementById('invoiceCurrency');
    const rateInput   = document.getElementById('exchangeRate');
    const invoiceDate = document.getElementById('invoiceDate');
    const csrf        = document.querySelector('meta[name="csrf-token"]').content;

    function fmt(n) { return parseFloat(n || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    // ── Customer change: tax-exempt alert + billing-party auto-fill ───────────
    function onCustomerChange() {
        const opt = customerSel.selectedOptions[0];
        if (!opt) return;
        document.getElementById('taxExemptAlert').classList.toggle('d-none', opt.dataset.taxExempt !== '1');
        const bpId = opt.dataset.billingPartyId;
        if (bpId && window.jQuery) { jQuery(billingSel).val(bpId).trigger('change.select2'); }
        showBillingParty();
    }
    function showBillingParty() {
        const opt = billingSel.selectedOptions[0];
        const box = document.getElementById('billingPartyInfo');
        const addr = opt ? (opt.dataset.address || '') : '';
        if (opt && opt.value && addr) {
            document.getElementById('billingPartyAddress').textContent = addr;
            box.classList.remove('d-none');
        } else {
            box.classList.add('d-none');
        }
    }

    // ── Exchange rate, linked to the daily exchange-rate master ───────────────
    function setRateNote(msg, kind) {
        const el = document.getElementById('rateNote');
        el.className = 'text-' + (kind || 'muted');
        el.innerHTML = '<i class="bi bi-info-circle me-1"></i>' + msg;
    }
    async function loadRate() {
        const currency = currencySel.value;
        document.getElementById('rateLabel').textContent = currency + ' → LKR Rate';
        document.getElementById('ratePrefixLabel').textContent = '1 ' + currency + ' =';
        if (currency === 'LKR') {
            rateInput.value = '1.0000'; rateInput.readOnly = true;
            setRateNote('LKR is the base currency', 'muted');
            return;
        }
        rateInput.readOnly = false;
        const spinner = document.getElementById('rateSpinner');
        spinner.classList.remove('d-none');
        try {
            const url = '{{ route("finance.fx-rate") }}?currency=' + encodeURIComponent(currency)
                      + '&date=' + encodeURIComponent(invoiceDate.value || '');
            const d = await (await fetch(url)).json();
            if (d.rate) {
                rateInput.value = parseFloat(d.rate).toFixed(4);
                setRateNote('Auto-loaded: 1 ' + currency + ' = ' + parseFloat(d.rate).toFixed(4) + ' LKR', 'success');
            } else {
                setRateNote('No rate found for this date — enter manually', 'warning');
            }
        } catch (e) {
            setRateNote('Rate lookup failed — enter manually', 'warning');
        }
        spinner.classList.add('d-none');
    }

    // ── Bill type → default currency, then refresh the rate ───────────────────
    function onServiceTypeChange() {
        currencySel.value = serviceSel.value === 'pti' ? 'USD' : 'LKR';
        loadRate();
    }

    if (window.jQuery) {
        jQuery(customerSel).on('change', onCustomerChange);
        jQuery(billingSel).on('change', showBillingParty);
    } else {
        customerSel.addEventListener('change', onCustomerChange);
        billingSel.addEventListener('change', showBillingParty);
    }
    serviceSel.addEventListener('change', onServiceTypeChange);
    currencySel.addEventListener('change', loadRate);
    invoiceDate.addEventListener('change', loadRate);

    // ── Preview ───────────────────────────────────────────────────────────────
    previewBtn.addEventListener('click', function () {
        const customerId = customerSel.value;
        const from       = document.getElementById('periodFrom').value;
        const to         = document.getElementById('periodTo').value;

        if (!customerId || !from || !to) { alert('Please select customer and billing period.'); return; }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';

        fetch('{{ route("billing.reefer.preview") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({
                customer_id: customerId,
                service_type: serviceSel.value,
                period_from: from, period_to: to,
                invoice_currency: currencySel.value,
                exchange_rate: rateInput.value,
                sscl_pct: document.getElementById('ssclPct').value,
                vat_pct: document.getElementById('vatPct').value,
            }),
        })
        .then(r => r.json())
        .then(data => {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i>Preview Charges';

            previewMissing = data.missing_rates || [];
            const hasMissing = window.renderTariffMissing(document.getElementById('missingRatesPanel'), previewMissing);

            if (!data.lines || data.lines.length === 0) {
                previewCard.style.display = 'none';
                previewEmpty.style.display = hasMissing ? 'none' : '';
                createWrap.style.display = 'none';
                return;
            }

            previewEmpty.style.display = 'none';
            previewCard.style.display = '';
            createWrap.style.display = hasMissing ? 'none' : '';

            const skipped = document.getElementById('previewSkipped');
            if (data.skipped > 0) { skipped.style.display = ''; skipped.textContent = data.skipped + ' session(s) skipped'; }
            else { skipped.style.display = 'none'; }

            const cur = data.invoice_currency;
            previewBody.innerHTML = '';
            data.lines.forEach(line => {
                const chargeable = line.billing_mode === 'hourly' ? line.chargeable_hours + ' hrs' : line.chargeable_days + ' days';
                const rateLabel  = line.billing_mode === 'hourly' ? line.currency + ' ' + fmt(line.rate) + '/hr' : line.currency + ' ' + fmt(line.rate) + '/day';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-monospace">${line.container_no}</td>
                    <td class="small text-nowrap">${line.plug_in_at ? new Date(line.plug_in_at).toLocaleString() : '—'}</td>
                    <td class="small text-nowrap">${line.plug_out_at ? new Date(line.plug_out_at).toLocaleString() : '—'}</td>
                    <td><span class="badge bg-light border text-muted text-capitalize">${line.billing_mode}</span></td>
                    <td class="small">${chargeable}</td>
                    <td class="text-end small font-monospace">${rateLabel}</td>
                    <td class="text-end small font-monospace">${cur} ${fmt(line.subtotal_display)}</td>
                    <td class="text-end small font-monospace">${cur} ${fmt(line.line_total)}</td>
                    <td class="text-end small font-monospace text-muted">LKR ${fmt(line.line_value)}</td>
                `;
                previewBody.appendChild(tr);
            });

            document.getElementById('sumSubtotal').textContent = cur + ' ' + fmt(data.subtotal);
            document.getElementById('sumSscl').textContent = cur + ' ' + fmt(data.sscl_amount) + ' (' + data.sscl_percentage + '%)';
            document.getElementById('sumVat').textContent  = cur + ' ' + fmt(data.vat_amount)  + ' (' + data.vat_percentage  + '%)';
            document.getElementById('sumTotal').textContent = cur + ' ' + fmt(data.total_amount);
            document.getElementById('sumValue').textContent = 'LKR ' + fmt(data.total_value);
        })
        .catch(() => {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i>Preview Charges';
            alert('Preview failed. Please try again.');
        });
    });

    // Block save when unresolved missing rates exist (server also re-checks)
    form.addEventListener('submit', function (e) {
        if (previewMissing.length > 0) {
            e.preventDefault();
            if (window.showToast) showToast('Cannot save — missing tariff rates. Update the tariff and preview again.', 'danger');
        }
    });

    // Initial sync — deferred so it runs AFTER the layout's DOMContentLoaded
    // Select2 initialisation (jQuery's ready fires before that handler), otherwise
    // the billing-party auto-fill would set a value the Select2 widget hasn't yet
    // rendered.
    function initSync() { onServiceTypeChange(); onCustomerChange(); }
    if (window.jQuery) { jQuery(function () { setTimeout(initSync, 0); }); }
    else { window.addEventListener('load', initSync); }
})();
</script>
@endpush
