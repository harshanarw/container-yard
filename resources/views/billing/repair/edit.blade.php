@extends('layouts.app')

@section('title', 'Edit ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Billing</li>
    <li class="breadcrumb-item"><a href="{{ route('billing.repair.index') }}">Repair Billing</a></li>
    <li class="breadcrumb-item"><a href="{{ route('repair-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Periodic Bill — {{ $invoice->invoice_no }}</h4>
        <p class="text-muted mb-0 small">
            {{ $invoice->customer->name ?? '—' }} · {{ $invoice->currency }} · draft
        </p>
    </div>
    <a href="{{ route('repair-invoices.show', $invoice) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('billing.repair.update', $invoice) }}" id="editForm">
    @csrf
    @method('PUT')
    <div class="row g-3">
        {{-- ── Header ── --}}
        <div class="col-lg-4">
            <div class="card content-card h-100">
                <div class="card-header py-2"><i class="bi bi-sliders me-2 text-primary"></i>Invoice Header</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Customer</label>
                        <input type="text" class="form-control form-control-sm" value="{{ $invoice->customer->name ?? '—' }}" disabled>
                        <div class="form-text">Customer and currency are fixed for a periodic bill.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Billing Party <span class="text-muted">(optional)</span></label>
                        <select name="billing_party_id" class="form-select form-select-sm">
                            <option value="">Same as customer</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" {{ (int) old('billing_party_id', $invoice->billing_party_id) === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $invoice->invoice_date?->toDateString()) }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Period From</label>
                            <input type="date" name="period_from" value="{{ old('period_from', $invoice->billing_period_from?->toDateString()) }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Period To</label>
                            <input type="date" name="period_to" value="{{ old('period_to', $invoice->billing_period_to?->toDateString()) }}" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $invoice->notes) }}</textarea>
                    </div>
                    <div class="alert alert-light border small mb-0">
                        <span id="editSummary">{{ $invoice->lines->count() }} line(s) currently on this invoice.</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Lines ── --}}
        <div class="col-lg-8">
            <div class="card content-card mb-3">
                <div class="card-header py-2"><i class="bi bi-list-check me-2 text-primary"></i>Current Lines <span class="text-muted small">(untick to remove)</span></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <tbody>
                                @forelse($invoice->lines as $line)
                                <tr>
                                    <td style="width:34px">
                                        <input class="form-check-input keep-line" type="checkbox" value="{{ $line->estimate_line_item_id }}" checked
                                               {{ $line->estimate_line_item_id ? '' : 'disabled' }}>
                                    </td>
                                    <td class="small">
                                        @if($line->container_no)<span class="font-monospace">{{ $line->container_no }}</span> · @endif
                                        @if($line->repairCategory)<span class="badge bg-secondary-subtle text-secondary border me-1">{{ $line->repairCategory->name }}</span>@endif
                                        {{ $line->description }}
                                        @unless($line->estimate_line_item_id)
                                            <span class="badge bg-warning-subtle text-warning border ms-1">manual line</span>
                                        @endunless
                                    </td>
                                    <td class="small text-end">{{ $invoice->currency }} {{ number_format($line->line_amount, 2) }}</td>
                                    <td class="small text-end text-muted">{{ $invoice->currency }} {{ number_format($line->gross_amount ?? $line->line_amount, 2) }}</td>
                                </tr>
                                @empty
                                <tr><td class="text-center text-muted py-3">This invoice has no lines.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card content-card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-plus-circle me-2 text-primary"></i>Add more billable repairs</span>
                </div>
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-2">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">Basis</label>
                            <select id="add_basis" class="form-select form-select-sm">
                                <option value="wo_completed">WO completed</option>
                                <option value="approved">Approved</option>
                                <option value="estimate" selected>Estimate date</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">From</label>
                            <input type="date" id="add_from" value="{{ now()->subYears(2)->toDateString() }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">To</label>
                            <input type="date" id="add_to" value="{{ now()->addYear()->toDateString() }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <button type="button" id="findMoreBtn" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Find</button>
                        </div>
                    </div>
                    <div id="addResults" class="small text-muted">Click <strong>Find</strong> to list other unbilled repairs for this customer.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Save Changes</button>
    </div>
    <div id="lineInputs"></div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const form = document.getElementById('editForm');
    const money = (n) => (n ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function updateCount() {
        const keep = document.querySelectorAll('.keep-line:checked').length;
        const add = document.querySelectorAll('.add-line:checked').length;
        document.getElementById('editSummary').textContent = (keep + add) + ' line(s) will be billed (' + keep + ' kept, ' + add + ' added).';
    }
    document.querySelectorAll('.keep-line').forEach(c => c.addEventListener('change', updateCount));

    async function findMore() {
        const box = document.getElementById('addResults');
        box.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Loading…';
        try {
            const res = await fetch("{{ route('billing.repair.preview') }}", {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({
                    customer_id: {{ $invoice->customer_id }},
                    invoice_currency: "{{ $invoice->currency }}",
                    period_from: document.getElementById('add_from').value,
                    period_to: document.getElementById('add_to').value,
                    period_basis: document.getElementById('add_basis').value,
                }),
            });
            if (!res.ok) throw new Error('Lookup failed (' + res.status + ')');
            const data = await res.json();
            if (!data.estimates.length) { box.innerHTML = '<div class="text-muted">No other unbilled repairs found.</div>'; return; }
            let html = '<table class="table table-sm mb-0"><tbody>';
            data.estimates.forEach(est => {
                est.lines.forEach(l => {
                    html += '<tr><td style="width:34px"><input class="form-check-input add-line" type="checkbox" value="' + l.estimate_line_item_id + '"></td>'
                        + '<td class="small">' + (est.estimate_no ?? '') + ' · ' + (l.container_no ?? '') + ' · ' + (l.description ?? '') + '</td>'
                        + '<td class="small text-end">' + data.currency + ' ' + money(l.line_amount) + '</td></tr>';
                });
            });
            html += '</tbody></table>';
            box.innerHTML = html;
            document.querySelectorAll('.add-line').forEach(c => c.addEventListener('change', updateCount));
        } catch (e) {
            box.innerHTML = '<div class="alert alert-danger py-2 small mb-0">' + e.message + '</div>';
        }
    }
    document.getElementById('findMoreBtn').addEventListener('click', findMore);

    form.addEventListener('submit', function (e) {
        const keep = [...document.querySelectorAll('.keep-line:checked')].map(c => c.value);
        const add = [...document.querySelectorAll('.add-line:checked')].map(c => c.value);
        const all = [...new Set([...keep, ...add])].filter(v => v);
        if (!all.length) { e.preventDefault(); alert('Keep or add at least one line.'); return; }
        const holder = document.getElementById('lineInputs');
        holder.innerHTML = '';
        all.forEach(v => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = 'line_item_ids[]'; i.value = v;
            holder.appendChild(i);
        });
    });
})();
</script>
@endpush
