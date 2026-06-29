@extends('layouts.app')

@section('title', 'Credit Note ' . $arCreditNote->credit_note_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.ar-credit-notes.index') }}">AR Credit Notes</a></li>
    <li class="breadcrumb-item active">{{ $arCreditNote->credit_note_no }}</li>
@endsection

@section('content')
@php $b = \App\Models\ArCreditNote::statusBadge($arCreditNote->status); @endphp

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-arrow-counterclockwise me-2 text-primary"></i>{{ $arCreditNote->credit_note_no }}
            <span class="badge bg-{{ $b }}-subtle text-{{ $b }} ms-2 fs-6 text-capitalize">{{ $arCreditNote->status }}</span></h4>
        <p class="text-muted mb-0 small">{{ $arCreditNote->customer->name ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('finance.ar-credit-notes.approve')
        @if($arCreditNote->isDraft())
        <form method="POST" action="{{ route('finance.ar-credit-notes.approve', $arCreditNote) }}" onsubmit="return confirm('Approve and post credit note {{ $arCreditNote->credit_note_no }} to GL?')">
            @csrf<button class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Approve &amp; Post</button>
        </form>
        @elseif($arCreditNote->isApproved())
        <form method="POST" action="{{ route('finance.ar-credit-notes.cancel', $arCreditNote) }}" onsubmit="return confirm('Cancel this credit note? The GL journal will be reversed and any applications removed.')">
            @csrf<button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancel</button>
        </form>
        @endif
        @endcan
        @can('finance.ar-credit-notes.delete')
        @if($arCreditNote->isDraft())
        <form method="POST" action="{{ route('finance.ar-credit-notes.destroy', $arCreditNote) }}" onsubmit="return confirm('Delete this draft credit note?')">
            @csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
        </form>
        @endif
        @endcan
        @can('finance.ar-credit-notes.pdf')
        <a href="{{ route('finance.ar-credit-notes.pdf', $arCreditNote) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Print A4</a>
        <a href="{{ route('finance.ar-credit-notes.pdf', ['arCreditNote' => $arCreditNote, 'size' => 'half']) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-text me-1"></i>Half Page</a>
        @endcan
        @can('finance.ar-credit-notes.email')
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="bi bi-envelope me-1"></i>Email</button>
        @endcan
        <a href="{{ route('finance.ar-credit-notes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

@can('finance.ar-credit-notes.email')
<div class="modal fade" id="emailModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('finance.ar-credit-notes.email', $arCreditNote) }}" id="emailForm">
        @csrf
        <div class="modal-header"><h6 class="modal-title"><i class="bi bi-envelope me-1 text-primary"></i>Email Credit Note {{ $arCreditNote->credit_note_no }}</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-2"><label class="form-label small fw-semibold">To <span class="text-danger">*</span></label>
                <input type="email" name="to_email" class="form-control form-control-sm" required value="{{ $arCreditNote->customer->email ?? '' }}"></div>
            <div class="mb-2"><label class="form-label small fw-semibold">CC</label><input type="email" name="cc_email" class="form-control form-control-sm"></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Attachment Format</label>
                <select name="format" class="form-select form-select-sm"><option value="a4" selected>Full Page (A4)</option><option value="half">Half Page (slip)</option></select></div>
            <div class="mb-1"><label class="form-label small fw-semibold">Message</label><textarea name="message" rows="3" class="form-control form-control-sm" maxlength="1000"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Send</button></div>
    </form>
</div></div></div>
@endcan

@if(session('success'))<div class="alert alert-success alert-dismissible fade show py-2 small">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show py-2 small">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif

<div class="row g-3">
    <div class="col-md-5">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2"><strong class="small">Details</strong></div>
            <div class="card-body">
                <table class="table table-sm mb-0 small">
                    <tr><td class="text-muted">Date</td><td class="fw-semibold">{{ $arCreditNote->credit_date->format('d M Y') }}</td></tr>
                    <tr><td class="text-muted">Customer</td><td class="fw-semibold">{{ $arCreditNote->customer->name ?? '—' }}</td></tr>
                    <tr><td class="text-muted">Currency / Rate</td><td class="font-monospace">{{ $arCreditNote->currency }} @ {{ rtrim(rtrim(number_format($arCreditNote->exchange_rate,6,'.',''),'0'),'.') }}</td></tr>
                    <tr><td class="text-muted">Subtotal</td><td class="text-end font-monospace">{{ number_format($arCreditNote->subtotal,2) }}</td></tr>
                    <tr><td class="text-muted">Output VAT</td><td class="text-end font-monospace">{{ number_format($arCreditNote->tax_amount,2) }}</td></tr>
                    <tr><td class="text-muted fw-semibold">Total</td><td class="text-end fw-bold font-monospace">{{ $arCreditNote->currency }} {{ number_format($arCreditNote->total_amount,2) }}</td></tr>
                    <tr><td class="text-muted">Value ({{ \App\Models\CompanySetting::baseCurrency() }})</td><td class="text-end font-monospace">{{ number_format($arCreditNote->base_amount,2) }}</td></tr>
                    <tr><td class="text-muted">Applied / Unapplied</td><td class="text-end font-monospace">{{ number_format($arCreditNote->applied_total,2) }} / <span class="fw-semibold">{{ number_format($arCreditNote->unapplied,2) }}</span></td></tr>
                    @if($arCreditNote->reason)<tr><td class="text-muted">Reason</td><td>{{ $arCreditNote->reason }}</td></tr>@endif
                    @if($arCreditNote->journal)<tr><td class="text-muted">GL Journal</td><td class="font-monospace">{{ $arCreditNote->journal->journal_no ?? ('#'.$arCreditNote->journal_id) }}</td></tr>@endif
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2"><strong class="small">Lines</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 small">
                    <thead class="table-light"><tr><th>Description</th><th>Revenue Account</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        @foreach($arCreditNote->lines as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td class="text-muted small">{{ $line->revenueAccount ? $line->revenueAccount->code.' — '.$line->revenueAccount->name : 'Default' }}</td>
                            <td class="text-end font-monospace">{{ number_format($line->amount,2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Applications --}}
    <div class="col-12">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2"><strong class="small">Applied to Invoices</strong></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light"><tr><th>Invoice</th><th>Type</th><th class="text-end">Applied ({{ $arCreditNote->currency }})</th>@if($arCreditNote->isApproved())<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse($arCreditNote->applications as $app)
                        <tr>
                            <td class="font-monospace">#{{ $app->invoice_id }}</td>
                            <td>{{ ucwords(str_replace('-',' ',$app->invoice_type)) }}</td>
                            <td class="text-end font-monospace text-success">{{ number_format($app->applied_amount,2) }}</td>
                            @if($arCreditNote->isApproved())
                            <td class="text-end">
                                @can('finance.ar-credit-notes.edit')
                                <form method="POST" action="{{ route('finance.ar-credit-notes.applications.destroy', [$arCreditNote, $app]) }}" onsubmit="return confirm('Remove this application?')">
                                    @csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-x"></i></button>
                                </form>
                                @endcan
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center text-muted py-3 fst-italic">Not applied to any invoice yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($arCreditNote->isApproved() && $arCreditNote->unapplied > 0 && $pendingInvoices->isNotEmpty())
            @can('finance.ar-credit-notes.edit')
            <div class="card-footer bg-transparent">
                <form method="POST" action="{{ route('finance.ar-credit-notes.applications.store', $arCreditNote) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small mb-1 fw-semibold">Apply to invoice <span class="text-muted">(unapplied: {{ number_format($arCreditNote->unapplied,2) }})</span></label>
                        <select name="_inv" id="pendInv" class="form-select form-select-sm" required>
                            <option value="">— Select invoice —</option>
                            @foreach($pendingInvoices as $pi)
                            <option value="{{ $pi['type'] }}|{{ $pi['id'] }}" data-type="{{ $pi['type'] }}" data-id="{{ $pi['id'] }}" data-out="{{ $pi['outstanding'] }}">{{ $pi['label'] }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="invoice_type" id="invType"><input type="hidden" name="invoice_id" id="invId">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-semibold">Amount</label>
                        <input type="number" name="applied_amount" id="applyAmt" class="form-control form-control-sm text-end font-monospace" min="0.01" step="0.01" required>
                    </div>
                    <div class="col-md-3"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Apply</button></div>
                </form>
            </div>
            @endcan
            @endif
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('pendInv');
    if (!sel) return;
    sel.addEventListener('change', function () {
        const o = this.options[this.selectedIndex];
        document.getElementById('invType').value = o.dataset.type || '';
        document.getElementById('invId').value = o.dataset.id || '';
        const out = parseFloat(o.dataset.out || 0);
        const unapplied = {{ $arCreditNote->unapplied }};
        if (out > 0) document.getElementById('applyAmt').value = Math.min(out, unapplied).toFixed(2);
    });
})();
</script>
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('emailForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]'); const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
        fetch(form.action, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: new FormData(form) })
            .then(r => r.json().then(d => ({ ok: r.ok, d })))
            .then(({ ok, d }) => {
                if (ok && d.success) { bootstrap.Modal.getInstance(document.getElementById('emailModal'))?.hide(); }
                else { window.showToast ? showToast((d && d.message) || 'Email could not be sent.', 'danger') : alert((d && d.message) || 'Email could not be sent.'); }
            })
            .catch(() => { window.showToast ? showToast('Email could not be sent.', 'danger') : alert('Email could not be sent.'); })
            .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
    });
})();
</script>
@endpush
