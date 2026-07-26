@extends('layouts.app')

@section('title', 'New Periodic Repair Bill')

@section('breadcrumb')
    <li class="breadcrumb-item">Billing</li>
    <li class="breadcrumb-item"><a href="{{ route('billing.repair.index') }}">Repair Billing</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4><i class="bi bi-receipt-cutoff me-2 text-primary"></i>New Periodic Repair Bill</h4>
        <p class="text-muted mb-0 small">Consolidate a customer's approved, unbilled repairs over a period into one invoice.</p>
    </div>
    <a href="{{ route('billing.repair.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('billing.repair.store') }}" id="billForm">
    @csrf
    <div class="row g-3">
        {{-- ── Parameters ── --}}
        <div class="col-lg-4">
            <div class="card content-card h-100">
                <div class="card-header py-2"><i class="bi bi-sliders me-2 text-primary"></i>Parameters</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-select select2 s2-code" data-s2-sel="name" required>
                            <option value="">Select customer…</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" data-billing-party="{{ $c->billing_party_id }}">[{{ $c->code }}] {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Billing Party <span class="text-muted">(optional)</span></label>
                        <select name="billing_party_id" id="billing_party_id" class="form-select select2 s2-code" data-s2-sel="name">
                            <option value="">Same as customer</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">[{{ $c->code }}] {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Invoice Date <span class="text-danger">*</span></label>
                            <input type="date" name="invoice_date" id="invoice_date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Period Basis</label>
                            <select name="period_basis" id="period_basis" class="form-select select2">
                                <option value="wo_completed">Work-order completed</option>
                                <option value="approved">Estimate approved</option>
                                <option value="estimate">Estimate date</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Period From <span class="text-danger">*</span></label>
                            <input type="date" name="period_from" id="period_from" value="{{ now()->startOfMonth()->toDateString() }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Period To <span class="text-danger">*</span></label>
                            <input type="date" name="period_to" id="period_to" value="{{ now()->endOfMonth()->toDateString() }}" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Currency</label>
                            <select name="invoice_currency" id="invoice_currency" class="form-select select2">
                                @foreach($currencies as $code => $name)
                                    <option value="{{ $code }}" {{ $code === $baseCurrency ? 'selected' : '' }}>{{ $code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Exchange Rate</label>
                            <input type="number" step="0.0001" min="0" name="exchange_rate" id="exchange_rate" value="1" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small mb-0">Repair Categories <span class="text-muted">(none = all)</span></label>
                            @if($categories->isNotEmpty())
                            <span class="small">
                                <a href="#" id="catSelectAll" class="text-decoration-none">Select all</a>
                                <span class="text-muted">·</span>
                                <a href="#" id="catClear" class="text-decoration-none">Clear</a>
                            </span>
                            @endif
                        </div>
                        <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;column-count:2;column-gap:1rem;">
                            @forelse($categories as $cat)
                            <div class="form-check" style="break-inside:avoid;">
                                <input class="form-check-input cat-check" type="checkbox" name="bill_categories[]" value="{{ $cat->id }}" id="cat{{ $cat->id }}">
                                <label class="form-check-label small" for="cat{{ $cat->id }}">{{ $cat->name }}</label>
                            </div>
                            @empty
                            <div class="text-muted small" style="column-span:all;">No repair categories configured.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="only_completed_wo">
                        <label class="form-check-label small" for="only_completed_wo">Only completed work orders</label>
                    </div>
                    <button type="button" id="previewBtn" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search me-1"></i>Preview billable repairs
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Results / selection ── --}}
        <div class="col-lg-8">
            <div class="card content-card h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-2 text-primary"></i>Billable repairs</span>
                    <span class="small text-muted" id="summaryLabel">Run a preview to see eligible estimates.</span>
                </div>
                <div class="card-body">
                    <div id="results" class="text-muted small text-center py-5">
                        <i class="bi bi-search fs-2 d-block mb-2 opacity-25"></i>
                        Choose a customer and period, then click <strong>Preview</strong>.
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center d-none" id="createBar">
                    <span class="small text-muted" id="selectionLabel">0 line(s) selected</span>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-circle me-1"></i>Create Draft Invoice
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div id="lineInputs"></div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const form = document.getElementById('billForm');
    const results = document.getElementById('results');
    const createBar = document.getElementById('createBar');
    const money = (n) => (n ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function selectedCategories() {
        return [...document.querySelectorAll('.cat-check:checked')].map(c => parseInt(c.value, 10));
    }

    async function runPreview() {
        const customer = document.getElementById('customer_id').value;
        if (!customer) { results.innerHTML = '<div class="alert alert-warning py-2 small mb-0">Select a customer first.</div>'; return; }

        results.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div> Loading…</div>';
        createBar.classList.add('d-none');

        const payload = {
            customer_id: customer,
            period_from: document.getElementById('period_from').value,
            period_to: document.getElementById('period_to').value,
            period_basis: document.getElementById('period_basis').value,
            invoice_currency: document.getElementById('invoice_currency').value,
            exchange_rate: document.getElementById('exchange_rate').value,
            only_completed_wo: document.getElementById('only_completed_wo').checked,
            categories: selectedCategories(),
        };

        try {
            const res = await fetch("{{ route('billing.repair.preview') }}", {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('Preview failed (' + res.status + ')');
            renderPreview(await res.json());
        } catch (e) {
            results.innerHTML = '<div class="alert alert-danger py-2 small mb-0">' + e.message + '</div>';
        }
    }

    function renderPreview(data) {
        const cur = data.currency;
        if (!data.estimates.length) {
            results.innerHTML = '<div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>No billable repairs match these filters.</div>';
            document.getElementById('summaryLabel').textContent = '0 estimates';
            return;
        }

        let html = '';
        data.estimates.forEach(est => {
            const badge = est.ready
                ? '<span class="badge bg-success-subtle text-success border">Ready · ' + est.wo_status + '</span>'
                : '<span class="badge bg-warning-subtle text-warning border">' + est.wo_status + '</span>';
            html += '<div class="border rounded mb-2">'
                + '<div class="d-flex align-items-center justify-content-between px-2 py-1 bg-light">'
                + '<div class="form-check mb-0"><input class="form-check-input est-toggle" type="checkbox" checked id="est' + est.estimate_id + '">'
                + '<label class="form-check-label small fw-semibold" for="est' + est.estimate_id + '">'
                + (est.estimate_no ?? ('#' + est.estimate_id)) + ' · ' + (est.container_no ?? '') + '</label></div>'
                + '<div class="d-flex align-items-center gap-2">' + badge
                + '<span class="small fw-semibold">' + cur + ' ' + money(est.grand_total) + '</span></div></div>'
                + '<table class="table table-sm mb-0"><tbody>';
            est.lines.forEach(l => {
                html += '<tr>'
                    + '<td style="width:34px"><input class="form-check-input line-check" type="checkbox" checked data-est="' + est.estimate_id + '" value="' + l.estimate_line_item_id + '"></td>'
                    + '<td class="small">' + (l.category ? '<span class="badge bg-secondary-subtle text-secondary border me-1">' + l.category + '</span>' : '') + (l.description ?? '') + '</td>'
                    + '<td class="small text-end">' + cur + ' ' + money(l.line_amount) + '</td>'
                    + '<td class="small text-end text-muted">' + cur + ' ' + money(l.gross) + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table></div>';
        });
        results.innerHTML = html;

        document.getElementById('summaryLabel').textContent =
            data.totals.estimates + ' estimate(s) · ' + cur + ' ' + money(data.totals.grand_total);
        createBar.classList.remove('d-none');

        // Estimate-level toggle checks/unchecks all its lines.
        document.querySelectorAll('.est-toggle').forEach(t => t.addEventListener('change', () => {
            document.querySelectorAll('.line-check[data-est="' + t.id.replace('est', '') + '"]').forEach(c => c.checked = t.checked);
            updateSelection();
        }));
        document.querySelectorAll('.line-check').forEach(c => c.addEventListener('change', updateSelection));
        updateSelection();
    }

    function updateSelection() {
        const n = document.querySelectorAll('.line-check:checked').length;
        document.getElementById('selectionLabel').textContent = n + ' line(s) selected';
    }

    // Auto-fill Billing Party from the selected customer (overridable). Mirrors
    // the Storage & Handling behaviour: listen to both change and select2:select,
    // read the option via jQuery, and fall back to the customer itself when no
    // explicit billing party is configured so a value is always shown.
    if (window.jQuery) {
        jQuery(function ($) {
            $('#customer_id').on('change select2:select', function () {
                const val  = $(this).val();
                const $opt = $(this).find('option[value="' + val + '"]');
                const bpId = val ? (($.trim($opt.attr('data-billing-party') || '')) || val) : '';
                $('#billing_party_id').val(bpId).trigger('change');
            });
        });
    }

    // Category quick-select links.
    const setAllCats = (checked) => document.querySelectorAll('.cat-check').forEach(c => c.checked = checked);
    document.getElementById('catSelectAll')?.addEventListener('click', (e) => { e.preventDefault(); setAllCats(true); });
    document.getElementById('catClear')?.addEventListener('click', (e) => { e.preventDefault(); setAllCats(false); });

    document.getElementById('previewBtn').addEventListener('click', runPreview);

    // Inject the selected line ids into the form just before it submits.
    form.addEventListener('submit', function (e) {
        const checked = [...document.querySelectorAll('.line-check:checked')];
        if (!checked.length) {
            e.preventDefault();
            alert('Select at least one line to bill.');
            return;
        }
        const holder = document.getElementById('lineInputs');
        holder.innerHTML = '';
        checked.forEach(c => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'line_item_ids[]';
            input.value = c.value;
            holder.appendChild(input);
        });
    });
})();
</script>
@endpush
