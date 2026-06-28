@extends('layouts.app')

@section('title', 'Receipt ' . $receipt->receipt_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.receipts.index') }}">Receipts</a></li>
    <li class="breadcrumb-item active">{{ $receipt->receipt_no }}</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4>
            <i class="bi bi-receipt me-2 text-primary"></i>{{ $receipt->receipt_no }}
            @php $rsc = \App\Models\Receipt::statusBadge($receipt->status); @endphp
        <span class="badge bg-{{ $rsc }}-subtle text-{{ $rsc }} ms-2 fs-6 text-capitalize">{{ $receipt->status }}</span>
        </h4>
        <p class="text-muted mb-0 small">{{ $receipt->customer->name ?? '—' }}</p>
    </div>
    <div class="d-flex gap-2">
        @can('finance.receipts.confirm')
        @if($receipt->isDraft())
        <form method="POST" action="{{ route('finance.receipts.confirm', $receipt) }}">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Confirm and post receipt {{ $receipt->receipt_no }} to GL?')">
                <i class="bi bi-check2-circle me-1"></i>Confirm & Post
            </button>
        </form>
        @endif
        @endcan
        @can('finance.receipts.void')
        @if($receipt->isConfirmed())
        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#voidModal">
            <i class="bi bi-x-circle me-1"></i>Void
        </button>
        @endif
        @endcan
        @can('finance.receipts.pdf')
        <a href="{{ route('finance.receipts.pdf', $receipt) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Print A4
        </a>
        <a href="{{ route('finance.receipts.pdf', ['receipt' => $receipt, 'size' => 'half']) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-file-earmark-text me-1"></i>Half Page
        </a>
        @endcan
        @can('finance.receipts.email')
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#emailModal">
            <i class="bi bi-envelope me-1"></i>Email
        </button>
        @endcan
        <a href="{{ route('finance.receipts.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@can('finance.receipts.email')
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('finance.receipts.email', $receipt) }}" id="emailForm">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-envelope me-1 text-primary"></i>Email Receipt {{ $receipt->receipt_no }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">To <span class="text-danger">*</span></label>
                        <input type="email" name="to_email" class="form-control form-control-sm" required
                               value="{{ $receipt->customer->email ?? '' }}">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">CC</label>
                        <input type="email" name="cc_email" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Attachment Format</label>
                        <select name="format" class="form-select form-select-sm">
                            <option value="a4" selected>Full Page (A4)</option>
                            <option value="half">Half Page (slip)</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Message</label>
                        <textarea name="message" rows="3" class="form-control form-control-sm" maxlength="1000" placeholder="Optional note to include in the email"></textarea>
                    </div>
                    <div class="form-text small">The selected receipt PDF is attached automatically (computer-generated copy — no signature lines).</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-3">
    {{-- Receipt Details --}}
    <div class="col-md-6">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2">
                <strong class="small">Receipt Details</strong>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <td class="text-muted w-40">Receipt No</td>
                        <td class="fw-semibold font-monospace">{{ $receipt->receipt_no }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Date</td>
                        <td>{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer</td>
                        <td class="fw-semibold">{{ $receipt->customer->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Amount</td>
                        <td class="fw-semibold">{{ number_format($receipt->amount, 2) }} {{ $receipt->currency }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Exchange Rate</td>
                        <td class="font-monospace">{{ $receipt->exchange_rate }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Value ({{ \App\Models\CompanySetting::baseCurrency() }})</td>
                        <td class="fw-semibold font-monospace">{{ number_format($receipt->base_amount ?? ($receipt->amount * $receipt->exchange_rate), 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Payment Method</td>
                        <td>{{ \App\Models\Receipt::paymentMethodLabel($receipt->payment_method) }}</td>
                    </tr>
                    @if($receipt->cheque_no)
                    <tr>
                        <td class="text-muted">Cheque No</td>
                        <td class="font-monospace">{{ $receipt->cheque_no }}</td>
                    </tr>
                    @endif
                    @if($receipt->reference_no)
                    <tr>
                        <td class="text-muted">Reference No</td>
                        <td class="font-monospace">{{ $receipt->reference_no }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Bank Account</td>
                        <td>{{ $receipt->bankAccount ? $receipt->bankAccount->account_name . ' — ' . $receipt->bankAccount->bank_name : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Narration</td>
                        <td>{{ $receipt->narration }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Created By</td>
                        <td>{{ $receipt->createdBy->name ?? '—' }}</td>
                    </tr>
                    @if($receipt->voidedBy)
                    <tr>
                        <td class="text-muted">Voided By</td>
                        <td class="text-danger">{{ $receipt->voidedBy->name }} on {{ $receipt->voided_at?->format('d M Y') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- GL Journal --}}
    <div class="col-md-6">
        <div class="card content-card h-100">
            <div class="card-header bg-transparent py-2">
                <strong class="small">GL Journal</strong>
            </div>
            <div class="card-body p-0">
                @if($receipt->journal)
                <div class="px-3 pt-2 pb-1 small text-muted">
                    Journal: <span class="fw-semibold font-monospace">{{ $receipt->journal->journal_no }}</span>
                    &nbsp;|&nbsp;
                    <span class="badge bg-{{ $receipt->journal->isPosted() ? 'success' : 'secondary' }}-subtle text-{{ $receipt->journal->isPosted() ? 'success' : 'secondary' }}">
                        {{ ucfirst($receipt->journal->status) }}
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Account</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipt->journal->entries as $entry)
                            <tr>
                                <td>
                                    <span class="font-monospace text-muted">{{ $entry->account->code }}</span>
                                    {{ $entry->account->name }}
                                </td>
                                <td class="text-end font-monospace">{{ $entry->debit > 0 ? number_format($entry->debit, 2) : '—' }}</td>
                                <td class="text-end font-monospace">{{ $entry->credit > 0 ? number_format($entry->credit, 2) : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center text-muted py-4 small">
                    <i class="bi bi-journal-x d-block fs-3 mb-1 opacity-25"></i>
                    Not yet posted to GL.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Allocations --}}
    <div class="col-12">
        <div class="card content-card">
            <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong class="small">Invoice Allocations</strong>
                <div class="d-flex gap-3 small">
                    <span class="text-muted">
                        Receipt total: <span class="fw-semibold font-monospace">{{ $receipt->currency }} {{ number_format($receipt->amount, 2) }}</span>
                    </span>
                    <span class="{{ $totalAllocated > 0 ? 'text-success' : 'text-muted' }}">
                        Allocated: <span class="fw-semibold font-monospace">{{ number_format($totalAllocated, 2) }}</span>
                    </span>
                    @if($unallocatedAmount > 0)
                    <span class="text-warning">
                        Unallocated: <span class="fw-semibold font-monospace">{{ number_format($unallocatedAmount, 2) }}</span>
                    </span>
                    @else
                    <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Fully Allocated</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice</th>
                                <th>Type</th>
                                <th class="text-end">Invoice Total</th>
                                <th class="text-end">Allocated</th>
                                <th class="text-end">Outstanding</th>
                                <th>Notes</th>
                                @if($receipt->isDraft()) <th></th> @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receipt->allocations as $alloc)
                            @php
                                // Resolve invoice for display (best-effort — may not exist)
                                try {
                                    $allocInvoice = app(\App\Services\Finance\ArAllocationService::class)
                                        ->resolveInvoice($alloc->invoice_type, $alloc->invoice_id);
                                    $allocTotal = app(\App\Services\Finance\ArAllocationService::class)
                                        ->getTotal($allocInvoice, $alloc->invoice_type);
                                    $allocOutstanding = app(\App\Services\Finance\ArAllocationService::class)
                                        ->getOutstanding($allocInvoice, $alloc->invoice_type);
                                    $allocInvoiceNo = $allocInvoice->invoice_no ?? "#{$alloc->invoice_id}";
                                    $allocInvoiceRoute = match($alloc->invoice_type) {
                                        'storage'          => route('billing.show', $alloc->invoice_id),
                                        'storage-handling' => route('billing.storage-handling.show', $alloc->invoice_id),
                                        'reefer'           => route('billing.reefer.show', $alloc->invoice_id),
                                        'repair'           => route('repair-invoices.show', $alloc->invoice_id),
                                        default            => null,
                                    };
                                } catch (\Throwable) {
                                    $allocInvoice = null; $allocTotal = 0; $allocOutstanding = 0;
                                    $allocInvoiceNo = "#{$alloc->invoice_id}"; $allocInvoiceRoute = null;
                                }
                            @endphp
                            <tr>
                                <td>
                                    @if($allocInvoiceRoute)
                                    <a href="{{ $allocInvoiceRoute }}" class="fw-semibold font-monospace text-decoration-none">
                                        {{ $allocInvoiceNo }}
                                    </a>
                                    @else
                                    <span class="font-monospace text-muted">{{ $allocInvoiceNo }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary small">
                                        {{ ucwords(str_replace('-', ' ', $alloc->invoice_type)) }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace">{{ number_format($allocTotal, 2) }}</td>
                                <td class="text-end font-monospace fw-semibold text-success">{{ number_format($alloc->allocated_amount, 2) }}</td>
                                <td class="text-end font-monospace {{ $allocOutstanding > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ number_format($allocOutstanding, 2) }}
                                </td>
                                <td class="text-muted">{{ $alloc->notes ?? '—' }}</td>
                                @if($receipt->isDraft())
                                <td class="text-end">
                                    @can('finance.receipts.create')
                                    <form method="POST"
                                          action="{{ route('finance.receipts.allocations.destroy', [$receipt, $alloc]) }}"
                                          onsubmit="return confirm('Remove this allocation?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-xs py-0 px-1 small">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $receipt->isDraft() ? 7 : 6 }}"
                                    class="text-center text-muted py-3 small fst-italic">
                                    No allocations yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($receipt->isDraft())
            @can('finance.receipts.create')
            <div class="card-footer bg-transparent pt-3">
                @if($pendingInvoices->isNotEmpty())
                <form method="POST" action="{{ route('finance.receipts.allocations.store', $receipt) }}"
                      class="row g-2 align-items-end" id="allocForm">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small mb-1 fw-semibold">
                            Outstanding Invoice
                            <span class="text-muted fw-normal">({{ $pendingInvoices->count() }} pending for {{ $receipt->customer->name ?? 'this customer' }})</span>
                        </label>
                        <select name="_pending_key" class="form-select form-select-sm" id="pendingInvoiceSelect" required>
                            <option value="">— Select invoice —</option>
                            @foreach($pendingInvoices as $pi)
                            <option value="{{ $pi['type'] }}|{{ $pi['id'] }}"
                                    data-type="{{ $pi['type'] }}"
                                    data-id="{{ $pi['id'] }}"
                                    data-outstanding="{{ $pi['outstanding'] }}">
                                {{ $pi['label'] }}
                            </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="invoice_type" id="allocInvoiceType">
                        <input type="hidden" name="invoice_id"   id="allocInvoiceId">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1 fw-semibold">
                            Amount
                            @if($unallocatedAmount > 0)
                            <span class="text-muted fw-normal">(receipt balance: {{ number_format($unallocatedAmount, 2) }})</span>
                            @endif
                        </label>
                        <input type="number" name="allocated_amount" id="allocAmount"
                               class="form-control form-control-sm" min="0.01" step="0.01"
                               placeholder="0.00" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </form>
                @else
                <p class="text-muted small mb-0 fst-italic">
                    <i class="bi bi-info-circle me-1"></i>
                    No outstanding invoices found for {{ $receipt->customer->name ?? 'this customer' }}.
                </p>
                @endif
            </div>
            @endcan
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('pendingInvoiceSelect');
    if (!sel) return;

    sel.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        document.getElementById('allocInvoiceType').value = opt.dataset.type ?? '';
        document.getElementById('allocInvoiceId').value   = opt.dataset.id ?? '';

        const outstanding = parseFloat(opt.dataset.outstanding ?? 0);
        const amtInput    = document.getElementById('allocAmount');
        if (outstanding > 0) {
            // Pre-fill with the lesser of outstanding and unallocated receipt balance
            const receiptBalance = {{ (float) $unallocatedAmount }};
            amtInput.value = Math.min(outstanding, receiptBalance).toFixed(2);
            amtInput.max   = outstanding;
        } else {
            amtInput.value = '';
            amtInput.removeAttribute('max');
        }
    });
});
</script>
@endpush

{{-- Void Modal --}}
@can('finance.receipts.void')
@if($receipt->isConfirmed())
<div class="modal fade" id="voidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Void Receipt {{ $receipt->receipt_no }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('finance.receipts.void', $receipt) }}">
                @csrf
                <div class="modal-body">
                    <p class="small text-muted">This will create a reversal journal entry. This action cannot be undone.</p>
                    <div>
                        <label class="form-label small fw-semibold">Reason for voiding</label>
                        <input type="text" name="reason" class="form-control form-control-sm" maxlength="255" placeholder="Optional reason...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Void Receipt</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endcan

@endsection

@push('scripts')
<script>
// Email via AJAX so a slow SMTP send still gives instant toast feedback.
(function () {
    const form = document.getElementById('emailForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn  = form.querySelector('button[type="submit"]');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
            if (ok && d.success) {
                bootstrap.Modal.getInstance(document.getElementById('emailModal'))?.hide();
                // Success feedback comes from the standard system notification
                // (bell badge + real-time popup) — no extra toast needed here.
            } else {
                window.showToast ? showToast((d && d.message) || 'Email could not be sent.', 'danger') : alert((d && d.message) || 'Email could not be sent.');
            }
        })
        .catch(() => { window.showToast ? showToast('Email could not be sent.', 'danger') : alert('Email could not be sent.'); })
        .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
    });
})();
</script>
@endpush
