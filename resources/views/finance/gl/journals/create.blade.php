@extends('layouts.app')

@section('title', 'New Manual Journal')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.gl.journals.index') }}">GL Journals</a></li>
    <li class="breadcrumb-item active">New Journal</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-journal-plus me-2 text-primary"></i>New Manual Journal</h4>
        <p class="text-muted mb-0 small">Create a double-entry journal. It must balance in the base currency ({{ $baseCurrency }}). Pick a header currency + rate as the default for every line, and override any line's currency/rate when needed — amounts are entered in each line's currency and converted to {{ $baseCurrency }}.</p>
    </div>
    <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0 small">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('finance.gl.journals.store') }}" id="journalForm">
    @csrf

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card content-card">
                <div class="card-header fw-semibold small"><i class="bi bi-calendar3 me-2 text-secondary"></i>Journal Header</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Journal Date <span class="text-danger">*</span></label>
                        <input type="date" name="journal_date" class="form-control form-control-sm @error('journal_date') is-invalid @enderror"
                               value="{{ old('journal_date', date('Y-m-d')) }}" required>
                        @error('journal_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Narration <span class="text-danger">*</span></label>
                        <input type="text" name="narration" class="form-control form-control-sm @error('narration') is-invalid @enderror"
                               value="{{ old('narration') }}" required maxlength="255" placeholder="Journal description">
                        @error('narration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Currency</label>
                            <select name="currency" id="headerCurrency" class="form-select form-select-sm s2-code" data-s2-sel="name">
                                @foreach($currencies as $c)
                                <option value="{{ $c }}" data-code="{{ $c }}" data-name="{{ $currencyNames[$c] ?? $c }}" {{ old('currency', $baseCurrency) === $c ? 'selected' : '' }}>{{ $c }}</option>
                                @endforeach
                            </select>
                            <div class="form-text small">Default for all lines</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold small">Exchange Rate</label>
                            <input type="number" name="exchange_rate" id="headerRate" class="form-control form-control-sm"
                                   value="{{ old('exchange_rate', 1) }}" min="0.000001" step="0.000001">
                            <div class="form-text small">1 header ccy → {{ $baseCurrency }}</div>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="post_immediately" id="post_immediately" value="1"
                               {{ old('post_immediately') ? 'checked' : '' }}>
                        <label class="form-check-label small" for="post_immediately">
                            Post Immediately
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            {{-- Totals summary (base currency) --}}
            <div class="card content-card mb-3">
                <div class="card-body py-2">
                    <div class="d-flex gap-4 align-items-center flex-wrap">
                        <div>
                            <span class="text-muted small">Total Debit ({{ $baseCurrency }}):</span>
                            <strong class="ms-1 font-monospace" id="totalDebitDisplay">0.00</strong>
                        </div>
                        <div>
                            <span class="text-muted small">Total Credit ({{ $baseCurrency }}):</span>
                            <strong class="ms-1 font-monospace" id="totalCreditDisplay">0.00</strong>
                        </div>
                        <div>
                            <span class="text-muted small">Difference:</span>
                            <strong class="ms-1 font-monospace" id="differenceDisplay">0.00</strong>
                        </div>
                        <div class="ms-auto">
                            <span id="balanceStatus" class="badge bg-secondary-subtle text-secondary" style="font-size:.75rem;">Enter amounts</span>
                        </div>
                    </div>
                    <div id="perCurrencyRow" class="small text-muted mt-1"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lines table --}}
    <div class="card content-card mb-3">
        <div class="card-header fw-semibold small d-flex justify-content-between align-items-center">
            <span><i class="bi bi-table me-2 text-secondary"></i>Journal Lines</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-warning" id="fxBalanceBtn" disabled
                        title="Add a base-currency line to the FX gain/loss account that clears the remaining imbalance">
                    <i class="bi bi-currency-exchange me-1"></i>Add FX Line
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addRowBtn">
                    <i class="bi bi-plus-lg me-1"></i>Add Row
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="linesTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:26%;">Account</th>
                            <th style="width:9%;">Cur</th>
                            <th style="width:11%;">Rate</th>
                            <th style="width:12%;">Debit</th>
                            <th style="width:12%;">Credit</th>
                            <th style="width:12%;" class="text-end">Base ({{ $baseCurrency }})</th>
                            <th>Narration</th>
                            <th style="width:40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="linesBody">
                        {{-- Rows added by JS --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
            <i class="bi bi-save me-1"></i>Save Journal
        </button>
        <a href="{{ route('finance.gl.journals.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

{{-- Data for JS --}}
@php
$accountsByClass = $accounts->groupBy('classification');
$classOrder = ['asset','liability','equity','income','expense'];
@endphp
<script>
window._journalAccounts   = @json($accountsByClass);
window._journalClassOrder  = @json($classOrder);
window._journalCurrencies  = @json($currencies);
window._journalBaseCcy     = @json($baseCurrency);
window._fxAccounts         = @json($fxAccounts);
window._rateUrl            = @json(route('finance.fx-rate'));
</script>

@push('scripts')
<script>
$(function () {
    var accountsByClass = window._journalAccounts;
    var classOrder      = window._journalClassOrder;
    var currencies      = window._journalCurrencies;
    var baseCcy         = window._journalBaseCcy;
    var fxAccounts      = window._fxAccounts || {};
    var rateUrl         = window._rateUrl;
    var lineCount = 0;
    var lastBaseDiff = 0; // signed base debit - credit, for the FX helper

    // Look up the foreign→base rate for a currency (as of the journal date).
    // Calls cb(rate) on a configured rate, or cb(null) if none / on error.
    function fetchRate(currency, cb) {
        if (!rateUrl || !currency || currency === baseCcy) { cb(null); return; }
        var dateEl = document.querySelector('input[name="journal_date"]');
        $.getJSON(rateUrl, { currency: currency, date: dateEl ? dateEl.value : '' })
            .done(function (res) { cb(res && res.found ? res.rate : null); })
            .fail(function () { cb(null); });
    }

    function headerCcy()  { return document.getElementById('headerCurrency').value || baseCcy; }
    function headerRate() { return parseFloat(document.getElementById('headerRate').value) || 1; }
    function round2(x)    { return Math.round((x + Number.EPSILON) * 100) / 100; }

    function buildAccountOptions() {
        var html = '<option value="">— Select account —</option>';
        classOrder.forEach(function (cls) {
            if (!accountsByClass[cls]) return;
            html += '<optgroup label="' + cls.charAt(0).toUpperCase() + cls.slice(1) + '">';
            accountsByClass[cls].forEach(function (acc) {
                html += '<option value="' + acc.id + '">' + acc.code + ' — ' + acc.name + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    function buildCurrencyOptions(selected) {
        var html = '';
        currencies.forEach(function (c) {
            html += '<option value="' + c + '"' + (c === selected ? ' selected' : '') + '>' + c + '</option>';
        });
        return html;
    }

    // Enable/disable a line's rate input based on its currency (base = fixed 1).
    function syncRate(row) {
        var ccy = row.querySelector('.currency-input').value;
        var rateInp = row.querySelector('.rate-input');
        if (ccy === baseCcy) {
            rateInp.value = 1;
            rateInp.setAttribute('readonly', 'readonly');
            rateInp.classList.add('bg-light');
        } else {
            rateInp.removeAttribute('readonly');
            rateInp.classList.remove('bg-light');
        }
    }

    function addRow(vals) {
        vals = vals || {};
        var idx = lineCount++;
        var ccy  = vals.currency || headerCcy();
        var rate = vals.rate || (ccy === headerCcy() ? headerRate() : 1);

        var tr = document.createElement('tr');
        tr.setAttribute('data-line', idx);
        tr.innerHTML =
            '<td><select name="lines[' + idx + '][account_id]" class="form-select form-select-sm account-select" required>' + buildAccountOptions() + '</select></td>' +
            '<td><select name="lines[' + idx + '][currency]" class="form-select form-select-sm currency-input" data-overridden="0">' + buildCurrencyOptions(ccy) + '</select></td>' +
            '<td><input type="number" name="lines[' + idx + '][exchange_rate]" class="form-control form-control-sm rate-input" data-overridden="0" value="' + rate + '" min="0.000001" step="0.000001"></td>' +
            '<td><input type="number" name="lines[' + idx + '][debit]" class="form-control form-control-sm debit-input" value="' + (vals.debit || '') + '" min="0" step="0.0001" placeholder="0.00"></td>' +
            '<td><input type="number" name="lines[' + idx + '][credit]" class="form-control form-control-sm credit-input" value="' + (vals.credit || '') + '" min="0" step="0.0001" placeholder="0.00"></td>' +
            '<td class="text-end font-monospace small text-muted base-cell">0.00</td>' +
            '<td><input type="text" name="lines[' + idx + '][narration]" class="form-control form-control-sm" value="' + (vals.narration || '') + '" maxlength="255" placeholder="Optional"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 remove-row"><i class="bi bi-trash3"></i></button></td>';
        document.getElementById('linesBody').appendChild(tr);

        var $sel = $(tr).find('.account-select');
        $sel.select2({ theme: 'bootstrap-5', width: '100%', placeholder: '— Select account —' });
        if (vals.account_id) { $sel.val(vals.account_id).trigger('change'); }

        var ccySel  = tr.querySelector('.currency-input');
        var rateInp = tr.querySelector('.rate-input');
        if (vals.currency) { ccySel.setAttribute('data-overridden', '1'); }
        if (vals.rate)     { rateInp.setAttribute('data-overridden', '1'); }
        syncRate(tr);

        ccySel.addEventListener('change', function () {
            ccySel.setAttribute('data-overridden', '1');
            rateInp.setAttribute('data-overridden', '1');
            syncRate(tr);
            if (ccySel.value !== baseCcy) {
                fetchRate(ccySel.value, function (r) { if (r) { rateInp.value = r; } recalculate(); });
            } else {
                recalculate();
            }
        });
        rateInp.addEventListener('input', function () {
            rateInp.setAttribute('data-overridden', '1');
            recalculate();
        });
        tr.querySelector('.debit-input').addEventListener('input', recalculate);
        tr.querySelector('.credit-input').addEventListener('input', recalculate);
        tr.querySelector('.remove-row').addEventListener('click', function () {
            $sel.select2('destroy');
            tr.remove();
            recalculate();
        });
        recalculate();
    }

    function recalculate() {
        var totalBaseDebit = 0, totalBaseCredit = 0;
        var perCcy = {}; // ccy -> {d, c}

        document.querySelectorAll('#linesBody tr').forEach(function (row) {
            var d    = parseFloat(row.querySelector('.debit-input').value) || 0;
            var c    = parseFloat(row.querySelector('.credit-input').value) || 0;
            var rate = parseFloat(row.querySelector('.rate-input').value) || 1;
            var ccy  = row.querySelector('.currency-input').value;

            var baseD = round2(d * rate), baseC = round2(c * rate);
            row.querySelector('.base-cell').textContent =
                baseD > 0 ? baseD.toFixed(2) : (baseC > 0 ? '(' + baseC.toFixed(2) + ')' : '0.00');

            totalBaseDebit  += baseD;
            totalBaseCredit += baseC;

            if (d > 0 || c > 0) {
                if (!perCcy[ccy]) perCcy[ccy] = { d: 0, c: 0 };
                perCcy[ccy].d += d;
                perCcy[ccy].c += c;
            }
        });

        var diff = Math.abs(totalBaseDebit - totalBaseCredit);
        var balanced = totalBaseDebit > 0 && diff < 0.005;

        document.getElementById('totalDebitDisplay').textContent  = totalBaseDebit.toFixed(2);
        document.getElementById('totalCreditDisplay').textContent = totalBaseCredit.toFixed(2);

        var diffEl = document.getElementById('differenceDisplay');
        diffEl.textContent = diff.toFixed(2);
        diffEl.style.color = balanced ? 'inherit' : '#dc3545';

        // Per-currency transaction subtotals (only when a non-base currency is used).
        var ccyKeys = Object.keys(perCcy);
        var showPer = ccyKeys.some(function (k) { return k !== baseCcy; });
        var perEl = document.getElementById('perCurrencyRow');
        if (showPer) {
            perEl.innerHTML = 'By currency (transaction): ' + ccyKeys.map(function (k) {
                return '<span class="me-2"><strong>' + k + '</strong> Dr ' + perCcy[k].d.toFixed(2) + ' / Cr ' + perCcy[k].c.toFixed(2) + '</span>';
            }).join('');
        } else {
            perEl.innerHTML = '';
        }

        var statusEl = document.getElementById('balanceStatus');
        if (balanced) {
            statusEl.className = 'badge bg-success-subtle text-success';
            statusEl.textContent = 'Balanced in ' + baseCcy;
        } else if (totalBaseDebit === 0 && totalBaseCredit === 0) {
            statusEl.className = 'badge bg-secondary-subtle text-secondary';
            statusEl.textContent = 'Enter amounts';
        } else {
            statusEl.className = 'badge bg-danger-subtle text-danger';
            statusEl.textContent = 'Not balanced in ' + baseCcy;
        }

        // FX balancing helper: available when a foreign currency is present, the
        // base is out of balance, and the matching FX account is configured.
        lastBaseDiff = round2(totalBaseDebit - totalBaseCredit);
        var fxBtn = document.getElementById('fxBalanceBtn');
        var needAcc = lastBaseDiff > 0 ? fxAccounts.gain : fxAccounts.loss;
        fxBtn.disabled = !(showPer && Math.abs(lastBaseDiff) >= 0.005 && needAcc);

        document.getElementById('submitBtn').disabled = !balanced;
    }

    // Push the header currency/rate to every line that hasn't been overridden.
    function applyHeaderToLines(hc, hr) {
        document.querySelectorAll('#linesBody tr').forEach(function (row) {
            var ccySel  = row.querySelector('.currency-input');
            var rateInp = row.querySelector('.rate-input');
            if (ccySel.getAttribute('data-overridden') === '0') {
                ccySel.value = hc;
                if (rateInp.getAttribute('data-overridden') === '0') {
                    rateInp.value = (hc === baseCcy ? 1 : hr);
                }
                syncRate(row);
            }
        });
        recalculate();
    }

    // Header currency change → auto-fetch its rate, then propagate to lines.
    // headerCurrency is a select2 (s2-code) — bind via jQuery so select2 changes fire.
    $('#headerCurrency').on('change', function () {
        var hc = this.value;
        syncHeaderRateEnabled();
        if (hc === baseCcy) {
            document.getElementById('headerRate').value = 1;
            applyHeaderToLines(hc, 1);
        } else {
            fetchRate(hc, function (r) {
                var hr = r || headerRate();
                document.getElementById('headerRate').value = hr;
                applyHeaderToLines(hc, hr);
            });
        }
    });

    // Header rate change → push to non-overridden lines on the header currency.
    document.getElementById('headerRate').addEventListener('input', function () {
        var hc = headerCcy(), hr = parseFloat(this.value) || 1;
        document.querySelectorAll('#linesBody tr').forEach(function (row) {
            var ccySel  = row.querySelector('.currency-input');
            var rateInp = row.querySelector('.rate-input');
            if (rateInp.getAttribute('data-overridden') === '0' && ccySel.value === hc && hc !== baseCcy) {
                rateInp.value = hr;
            }
        });
        recalculate();
    });

    function syncHeaderRateEnabled() {
        var rateEl = document.getElementById('headerRate');
        if (headerCcy() === baseCcy) {
            rateEl.value = 1;
            rateEl.setAttribute('readonly', 'readonly');
            rateEl.classList.add('bg-light');
        } else {
            rateEl.removeAttribute('readonly');
            rateEl.classList.remove('bg-light');
        }
    }

    document.getElementById('addRowBtn').addEventListener('click', function () { addRow(); });

    // Add a base-currency line to the FX gain/loss account that clears the
    // remaining base imbalance (for genuine cross-currency journals).
    document.getElementById('fxBalanceBtn').addEventListener('click', function () {
        var diff = round2(lastBaseDiff);
        if (Math.abs(diff) < 0.005) return;
        if (diff > 0 && fxAccounts.gain) {
            // debits exceed credits → add a credit to FX gain
            addRow({ account_id: fxAccounts.gain.id, currency: baseCcy, rate: 1, credit: diff.toFixed(2), narration: 'Exchange gain on journal' });
        } else if (diff < 0 && fxAccounts.loss) {
            // credits exceed debits → add a debit to FX loss
            addRow({ account_id: fxAccounts.loss.id, currency: baseCcy, rate: 1, debit: Math.abs(diff).toFixed(2), narration: 'Exchange loss on journal' });
        }
    });

    syncHeaderRateEnabled();

    @if(old('lines'))
    @foreach(old('lines', []) as $i => $line)
    addRow({
        account_id: '{{ old("lines.$i.account_id") }}',
        currency:   '{{ old("lines.$i.currency") }}',
        rate:       '{{ old("lines.$i.exchange_rate") }}',
        debit:      '{{ old("lines.$i.debit") }}',
        credit:     '{{ old("lines.$i.credit") }}',
        narration:  '{{ old("lines.$i.narration") }}'
    });
    @endforeach
    @else
    addRow();
    addRow();
    @endif
});
</script>
@endpush

@endsection
