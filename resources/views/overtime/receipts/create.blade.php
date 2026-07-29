@extends('layouts.app')

@section('title', 'New Overtime Receipt')

@section('breadcrumb')
    <li class="breadcrumb-item">Overtime</li>
    <li class="breadcrumb-item"><a href="{{ route('overtime.receipts.index') }}">Receipts</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <h4><i class="bi bi-clock-history me-2 text-primary"></i>New Overtime Receipt</h4>
        <p class="text-muted mb-0 small">One receipt per BL for a selected overtime service window.</p>
    </div>
    <a href="{{ route('overtime.receipts.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

@if(session('error'))
<div class="alert alert-danger py-2 small">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('overtime.receipts.store') }}" id="otForm">
    @csrf
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card content-card h-100">
                <div class="card-header py-2"><i class="bi bi-pencil-square me-2 text-primary"></i>Receipt Details</div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label small mb-1">BL Number <span class="text-danger">*</span></label>
                        <input type="text" name="bl_number" class="form-control form-control-sm text-uppercase" required value="{{ old('bl_number') }}" placeholder="e.g. CMB1234567">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Customer (Consignee) <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select select2 s2-code" data-s2-sel="name" required>
                            <option value="">Select customer…</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}" {{ old('customer_id')==$c->id?'selected':'' }}>[{{ $c->code }}] {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Operational Date <span class="text-danger">*</span></label>
                            <input type="date" id="operational_date" name="operational_date" class="form-control form-control-sm" required value="{{ old('operational_date', now()->toDateString()) }}">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Gate-In Time <span class="text-muted">(optional)</span></label>
                            <input type="time" id="gate_in_time" class="form-control form-control-sm" value="18:00">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 mb-2">
                            <label class="form-label small mb-1">Container Count <span class="text-danger">*</span></label>
                            <input type="number" name="expected_container_count" min="1" max="999" value="{{ old('expected_container_count', 1) }}" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Remarks</label>
                        <textarea name="remarks" rows="2" class="form-control form-control-sm">{{ old('remarks') }}</textarea>
                    </div>
                    <button type="button" id="checkRules" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Check applicable OT rules</button>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card content-card h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-check me-2 text-primary"></i>Overtime Service Window</span>
                    <span class="small text-muted" id="dayCat"></span>
                </div>
                <div class="card-body">
                    <div id="ruleBox" class="text-muted small text-center py-5">
                        <i class="bi bi-search fs-2 d-block mb-2 opacity-25"></i>
                        Enter a BL, customer and date, then <strong>Check applicable OT rules</strong>.
                    </div>
                    <input type="hidden" name="tariff_rule_id" id="tariff_rule_id">
                </div>
                <div class="card-footer text-end d-none" id="genBar">
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-receipt me-1"></i>Generate Receipt</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const box = document.getElementById('ruleBox');
    const genBar = document.getElementById('genBar');
    const money = (n) => (n ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

    async function checkRules() {
        const date = document.getElementById('operational_date').value;
        if (!date) { box.innerHTML = '<div class="alert alert-warning py-2 small mb-0">Pick an operational date first.</div>'; return; }
        box.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';
        genBar.classList.add('d-none');
        try {
            const res = await fetch("{{ route('overtime.receipts.rules') }}", {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},
                body: JSON.stringify({operational_date: date, gate_in_time: document.getElementById('gate_in_time').value || null}),
            });
            if (!res.ok) throw new Error('Lookup failed (' + res.status + ')');
            render(await res.json());
        } catch (e) { box.innerHTML = '<div class="alert alert-danger py-2 small mb-0">' + e.message + '</div>'; }
    }

    function render(data) {
        document.getElementById('dayCat').textContent = (data.day_category || '').replace(/_/g, ' ');
        if (!data.rules.length) {
            box.innerHTML = '<div class="alert alert-warning py-2 small mb-0">No configured OT rule for this day' +
                (data.unconfigured ? ' (falls into an unconfigured period — supervisor approval / custom rule required).' : '.') + '</div>';
            return;
        }
        let html = '<div class="small text-muted mb-2">Select the service window the customer is paying for:</div>';
        data.rules.forEach(r => {
            html += '<label class="d-block border rounded p-2 mb-2" style="cursor:pointer;">'
                + '<input class="form-check-input me-2 rule-pick" type="radio" name="rulepick" value="' + r.id + '">'
                + '<span class="fw-semibold">' + r.label + '</span> <span class="badge bg-secondary-subtle text-secondary border ms-1">Period ' + r.period + '</span>'
                + '<div class="small text-muted mt-1">Valid ' + r.valid_from + ' → ' + r.valid_to + '</div>'
                + '<div class="fw-bold text-primary mt-1">' + r.currency + ' ' + money(r.rate) + '</div>'
                + '</label>';
        });
        box.innerHTML = html;
        document.querySelectorAll('.rule-pick').forEach(el => el.addEventListener('change', function () {
            document.getElementById('tariff_rule_id').value = this.value;
            genBar.classList.remove('d-none');
        }));
    }

    document.getElementById('checkRules').addEventListener('click', checkRules);
    document.getElementById('otForm').addEventListener('submit', function (e) {
        if (!document.getElementById('tariff_rule_id').value) { e.preventDefault(); alert('Select an overtime service window first.'); }
    });
})();
</script>
@endpush
