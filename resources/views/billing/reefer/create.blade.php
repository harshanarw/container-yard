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
                    <div class="mb-3">
                        <label class="form-label fw-medium">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customerId" class="form-select select2" required>
                            <option value="">— Select Customer —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-medium">Period From <span class="text-danger">*</span></label>
                            <input type="date" name="period_from" id="periodFrom" class="form-control" required value="{{ old('period_from') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Period To <span class="text-danger">*</span></label>
                            <input type="date" name="period_to" id="periodTo" class="form-control" required value="{{ old('period_to', today()->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-medium">Currency</label>
                            <input type="text" name="invoice_currency" id="invoiceCurrency" class="form-control font-monospace"
                                   value="{{ old('invoice_currency', $defaultCurrency) }}" maxlength="3">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Exchange Rate</label>
                            <input type="number" name="exchange_rate" id="exchangeRate" class="form-control"
                                   value="{{ old('exchange_rate', $exchangeRate) }}" step="0.0001" min="0.0001">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">SSCL % <small class="text-muted">(auto from charge code)</small></label>
                            <input type="number" name="sscl_pct" id="ssclPct" class="form-control" value="{{ old('sscl_pct', 0) }}" step="0.01" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label">VAT % <small class="text-muted">(auto from charge code)</small></label>
                            <input type="number" name="vat_pct" id="vatPct" class="form-control" value="{{ old('vat_pct', 0) }}" step="0.01" min="0" max="100">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" class="form-control" required value="{{ old('invoice_date', today()->format('Y-m-d')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                    <button type="button" id="previewBtn" class="btn btn-outline-primary w-100">
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
    let previewMissing = [];

    function fmt(n) { return parseFloat(n).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    previewBtn.addEventListener('click', function () {
        const customerId = document.getElementById('customerId').value;
        const from       = document.getElementById('periodFrom').value;
        const to         = document.getElementById('periodTo').value;
        const currency   = document.getElementById('invoiceCurrency').value;
        const rate       = document.getElementById('exchangeRate').value;
        const ssclPct    = document.getElementById('ssclPct').value;
        const vatPct     = document.getElementById('vatPct').value;

        if (!customerId || !from || !to) {
            alert('Please select customer and billing period.');
            return;
        }

        previewBtn.disabled = true;
        previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Loading...';

        fetch('{{ route("billing.reefer.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ customer_id: customerId, period_from: from, period_to: to,
                                   invoice_currency: currency, exchange_rate: rate,
                                   sscl_pct: ssclPct, vat_pct: vatPct }),
        })
        .then(r => r.json())
        .then(data => {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i>Preview Charges';

            // Missing tariff rates → detail panel; block creating the invoice.
            previewMissing = data.missing_rates || [];
            const hasMissing = window.renderTariffMissing(document.getElementById('missingRatesPanel'), previewMissing);

            if (!data.lines || data.lines.length === 0) {
                previewCard.style.display = 'none';
                // The panel explains the missing-rate reason; otherwise show the empty notice.
                previewEmpty.style.display = hasMissing ? 'none' : '';
                createWrap.style.display = 'none';
                return;
            }

            previewEmpty.style.display = 'none';
            previewCard.style.display = '';
            createWrap.style.display = hasMissing ? 'none' : '';

            const skipped = document.getElementById('previewSkipped');
            if (data.skipped > 0) {
                skipped.style.display = '';
                skipped.textContent = data.skipped + ' session(s) skipped (no tariff)';
            } else {
                skipped.style.display = 'none';
            }

            // Build rows
            previewBody.innerHTML = '';
            data.lines.forEach(line => {
                const chargeable = line.billing_mode === 'hourly'
                    ? line.chargeable_hours + ' hrs'
                    : line.chargeable_days + ' days';
                const rateLabel = line.billing_mode === 'hourly'
                    ? line.currency + ' ' + fmt(line.rate) + '/hr'
                    : line.currency + ' ' + fmt(line.rate) + '/day';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-monospace">${line.container_no}</td>
                    <td class="small text-nowrap">${line.plug_in_at ? new Date(line.plug_in_at).toLocaleString() : '—'}</td>
                    <td class="small text-nowrap">${line.plug_out_at ? new Date(line.plug_out_at).toLocaleString() : '—'}</td>
                    <td><span class="badge bg-light border text-muted text-capitalize">${line.billing_mode}</span></td>
                    <td class="small">${chargeable}</td>
                    <td class="text-end small font-monospace">${rateLabel}</td>
                    <td class="text-end small font-monospace">${line.currency} ${fmt(line.subtotal_display)}</td>
                    <td class="text-end small font-monospace">${line.currency} ${fmt(line.line_total)}</td>
                `;
                previewBody.appendChild(tr);
            });

            // Totals
            const cur = data.invoice_currency;
            document.getElementById('sumSubtotal').textContent = cur + ' ' + fmt(data.subtotal);
            document.getElementById('sumSscl').textContent = cur + ' ' + fmt(data.sscl_amount) + ' (' + data.sscl_percentage + '%)';
            document.getElementById('sumVat').textContent  = cur + ' ' + fmt(data.vat_amount)  + ' (' + data.vat_percentage  + '%)';
            document.getElementById('sumTotal').textContent = cur + ' ' + fmt(data.total_amount);
        })
        .catch(() => {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i>Preview Charges';
            alert('Preview failed. Please try again.');
        });
    });
})();
</script>
@endpush
