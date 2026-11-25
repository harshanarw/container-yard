@extends('layouts.app')

@section('title', 'New On-Hire In')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('yard.lessor-hires.index') }}">On-Hire In</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-box-arrow-in-down-right me-2 text-primary"></i>New On-Hire In</h4>
    <p class="text-muted mb-0 small">Take a container on hire from a lessor. This opens a dedicated job so the on-hire→off-hire period is costed on its own P&amp;L.</p>
</div>

@if(session('error'))
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card content-card">
    <div class="card-body">
        <form method="POST" action="{{ route('yard.lessor-hires.store') }}" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label class="form-label fw-semibold">Container <span class="text-danger">*</span></label>
                <select name="container_id" id="leaseContainer" class="form-select select2" required>
                    <option value="">- Select container -</option>
                    @foreach($containers as $c)
                        <option value="{{ $c->id }}" @selected(old('container_id') == $c->id)
                            data-no="{{ $c->container_no }}"
                            data-eqt="{{ $c->equipmentType?->eqt_code ?: trim($c->size . ' ' . $c->type_code) }}"
                            data-days="{{ $c->option_days }}"
                            data-customer="{{ $c->option_customer }}"
                            data-mr="{{ $c->mr_status ? \App\Support\MrStatusCatalogue::label($c->mr_status, $c->mr_lane) : '' }}">{{ $c->container_no }} - {{ $c->size }}' {{ $c->type_code }}@if($c->mr_status) · {{ \App\Support\MrStatusCatalogue::label($c->mr_status, $c->mr_lane) }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Lessor (shipping line) <span class="text-danger">*</span></label>
                <select name="lessor_id" id="leaseLessor" class="form-select select2" required>
                    <option value="">- Select lessor -</option>
                    @foreach($lessors as $l)
                        <option value="{{ $l->id }}" @selected(old('lessor_id') == $l->id)>{{ $l->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">The party the yard pays for the hire (an AP contact).</div>
                <div id="lessorWarning" class="alert alert-warning small py-2 mt-2 mb-0 d-none"></div>
            </div>

            {{-- The visit this lease would sit inside: filled when a container is picked. --}}
            <div class="col-12 d-none" id="leaseVisitPanel"></div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">On-Hire Date <span class="text-danger">*</span></label>
                <input type="date" name="on_hire_date" id="leaseOnHireDate" class="form-control" value="{{ old('on_hire_date', now()->toDateString()) }}" required>
                <div class="form-text" id="onHireDateHint">Today or earlier.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Expected Off-Hire <span class="text-muted fw-normal">(optional)</span></label>
                <input type="date" name="expected_off_hire_date" class="form-control" value="{{ old('expected_off_hire_date') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Hire Reference</label>
                <input type="text" name="hire_reference" class="form-control" maxlength="100" value="{{ old('hire_reference') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Per-Diem Rate <span class="text-muted fw-normal">(optional)</span></label>
                <input type="number" step="0.01" min="0" name="per_diem_rate" class="form-control" value="{{ old('per_diem_rate') }}" placeholder="For future accrual">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
            <div class="col-12">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>On save a job opens for this on-hire. Enter the lessor's fee as a <strong>supplier invoice or payment voucher</strong> and pick this job in its <em>Job (costing)</em> field - the cost then appears on the job's P&amp;L.
                </div>
                <button type="submit" id="leaseSubmit" class="btn btn-primary px-4"><i class="bi bi-check-circle me-1"></i>Open On-Hire</button>
                <a href="{{ route('yard.lessor-hires.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const lookupUrl  = @json(route('yard.lessor-hires.lookup'));
    const panel      = document.getElementById('leaseVisitPanel');
    const submit     = document.getElementById('leaseSubmit');
    const lessorWarn = document.getElementById('lessorWarning');
    const dateHint   = document.getElementById('onHireDateHint');
    const esc = v => String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let visit = null;

    // A lessor other than the line whose visit this is: allowed, but said.
    function checkLessor() {
        const chosen = $('#leaseLessor').val();
        const show = !!(visit && visit.visit_customer_id && chosen && String(chosen) !== String(visit.visit_customer_id));
        lessorWarn.classList.toggle('d-none', !show);
        if (show) {
            lessorWarn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>This lessor is not the customer whose visit this is (<strong>'
                + esc(visit.visit ? visit.visit.customer : '') + '</strong>). Go on only if this party really leases the box to the yard, for example its local agent.';
        }
    }

    function cell(label, value) {
        return value ? '<div class="col-sm-6 col-lg-3"><div class="text-muted" style="font-size:.72rem;">' + label + '</div><div class="small fw-semibold">' + value + '</div></div>' : '';
    }

    function render(d) {
        visit = d;
        const c = d.container, v = d.visit, j = d.main_job, st = d.storage;
        let html = '<div class="card border-primary-subtle"><div class="card-body py-3"><div class="row g-2">';
        html += cell('Container', esc(c.container_no) + ' · ' + esc(c.size_type));
        html += cell('Cargo / condition', esc(c.cargo_status) + (c.condition ? ' · ' + esc(c.condition) : '') + (c.grade ? ' · ' + esc(c.grade) : ''));
        html += cell('M&amp;R status', esc(c.mr_status));
        if (v) {
            html += cell('Gate in', esc(v.gate_in) + (v.eir_no ? ' <span class="text-muted font-monospace fw-normal" style="font-size:.7rem;">' + esc(v.eir_no) + '</span>' : ''));
            html += cell('Days in yard', esc(v.days_in_yard));
            html += cell('Visit customer', esc(v.customer));
        }
        if (j) {
            html += cell('Main job', '<a href="' + esc(j.url) + '" target="_blank" class="font-monospace">' + esc(j.job_no) + '</a> <span class="badge ' + esc(j.badge) + '" style="font-size:.65rem;">' + esc(j.status) + '</span>'
                + '<div class="text-muted fw-normal" style="font-size:.7rem;">' + esc(j.type) + '</div>');
        }
        if (st) {
            html += cell('Storage now', 'Since ' + esc(st.since) + ' · ' + esc(st.free_days) + ' free days · ' + esc(Number(st.daily_rate).toFixed(2)) + '/day');
        }
        html += cell('Storage invoiced to', d.invoiced_through ? esc(d.invoiced_through) : '<span class="text-muted fw-normal">nothing yet</span>');
        if (d.next_job_no) {
            html += cell('This lease will be', '<span class="font-monospace text-primary">' + esc(d.next_job_no) + '</span>');
        }
        html += '</div>';

        if (d.sub_jobs.length) {
            html += '<div class="small mt-2 pt-2 border-top"><span class="text-muted">Sub jobs of this visit:</span><ul class="mb-0 ps-3">'
                + d.sub_jobs.map(s => '<li><a href="' + esc(s.url) + '" target="_blank" class="font-monospace">' + esc(s.job_no) + '</a> · ' + esc(s.type)
                    + (s.party ? ' · ' + esc(s.party) : '') + ' <span class="badge ' + esc(s.badge) + '" style="font-size:.62rem;">' + esc(s.status) + '</span></li>').join('')
                + '</ul></div>';
        }
        if (d.blockers.length) {
            html += '<div class="alert alert-danger small mt-3 mb-0" id="leaseBlockers"><strong><i class="bi bi-x-octagon me-1"></i>This container cannot be taken on hire:</strong><ul class="mb-0 ps-3">'
                + d.blockers.map(b => '<li>' + esc(b) + '</li>').join('') + '</ul></div>';
        }
        if (d.warnings.length) {
            html += '<div class="alert alert-warning small mt-3 mb-0" id="leaseWarnings"><i class="bi bi-exclamation-triangle me-1"></i>'
                + d.warnings.map(esc).join('<br>') + '</div>';
        }
        html += '</div></div>';

        panel.innerHTML = html;
        panel.classList.remove('d-none');
        submit.disabled = d.blockers.length > 0;

        // On-hire date: backdating allowed down to the earliest day, never the future.
        const $date = $('#leaseOnHireDate');
        if ($date.data('datepicker')) {
            $date.datepicker('setStartDate', d.earliest_on_hire ? new Date(d.earliest_on_hire + 'T00:00:00') : false);
            $date.datepicker('setEndDate', new Date());
        }
        dateHint.textContent = d.earliest_on_hire
            ? 'From ' + d.earliest_on_hire + (d.invoiced_through ? ' (storage is invoiced to ' + d.invoiced_through + ')' : ' (the gate-in)') + ' up to today.'
            : 'Today or earlier.';

        // The lessor defaults to the line whose visit this is.
        if (d.visit_customer_id && !$('#leaseLessor').val()
            && $('#leaseLessor option[value="' + d.visit_customer_id + '"]').length) {
            $('#leaseLessor').val(String(d.visit_customer_id)).trigger('change');
        }
        checkLessor();
    }

    function lookup() {
        const id = $('#leaseContainer').val();
        visit = null;
        if (!id) {
            panel.classList.add('d-none');
            panel.innerHTML = '';
            submit.disabled = false;
            checkLessor();
            return;
        }
        panel.classList.remove('d-none');
        panel.innerHTML = '<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Looking up the visit...</div>';
        fetch(lookupUrl + '?container_id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(render)
            .catch(() => { panel.innerHTML = '<div class="alert alert-danger small mb-0">Could not look up this container. Try again.</div>'; });
    }

    // Container option: number + equipment badge + days badge + M&R + customer (as Gate Out).
    function containerResult(item) {
        if (!item.id || !item.element) return item.text;
        const d = item.element.dataset;
        const $el = $('<span class="d-flex align-items-center gap-2 py-1">');
        $('<strong class="font-monospace">').text(d.no || item.text).appendTo($el);
        if (d.eqt) {
            $('<span class="badge border font-monospace" style="background:#f0f9ff;color:#0369a1;border-color:#bae6fd!important;font-size:.68rem;">').text(d.eqt).appendTo($el);
        }
        if (d.days !== '') {
            const urgent = Number(d.days) >= 30;
            $('<span class="badge border" style="font-size:.68rem;' + (urgent ? 'background:#fff7ed;color:#c2410c;border-color:#fed7aa!important;' : 'background:#fefce8;color:#854d0e;border-color:#fde68a!important;') + '">').text(d.days + 'd').appendTo($el);
        }
        if (d.mr) {
            $('<span class="badge border" style="font-size:.68rem;background:#f5f3ff;color:#6d28d9;border-color:#ddd6fe!important;">').text(d.mr).appendTo($el);
        }
        if (d.customer) {
            $('<span class="text-muted ms-auto" style="font-size:.72rem;">').text(d.customer).appendTo($el);
        }
        return $el;
    }

    $(function () {
        const $cnt = $('#leaseContainer');
        if ($cnt.data('select2')) { $cnt.select2('destroy'); }
        $cnt.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Type to search containers in yard…',
            allowClear: true,
            templateResult: containerResult,
            templateSelection: item => (item.element && item.element.dataset.no) || item.text,
            // Match on the container number only, like the Gate Out search.
            matcher: function (params, data) {
                const term = $.trim(params.term || '').toUpperCase();
                if (!term) return data;
                const no = data.element && data.element.dataset.no;
                return no && no.toUpperCase().indexOf(term) > -1 ? data : null;
            },
        });

        $('#leaseContainer').on('change', lookup);
        $('#leaseLessor').on('change', checkLessor);
        if ($('#leaseContainer').val()) lookup();
    });
})();
</script>
@endpush
