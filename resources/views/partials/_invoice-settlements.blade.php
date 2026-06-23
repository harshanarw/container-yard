@php
    /**
     * Shows receipts that have been allocated to this invoice.
     * Required props: $invoiceType (string), $invoiceId (int), $invoiceTotal (float)
     * Optional:       $invoiceCurrency (string, default 'LKR')
     */
    use App\Models\ReceiptAllocation;

    $invoiceCurrency ??= 'LKR';
    $settlements = ReceiptAllocation::where('invoice_type', $invoiceType)
        ->where('invoice_id', $invoiceId)
        ->with(['receipt' => fn ($q) => $q->select('id','receipt_no','receipt_date','status','currency','exchange_rate')])
        ->orderBy('id')
        ->get();

    $totalSettled  = $settlements->filter(fn ($a) => in_array($a->receipt?->status, ['draft','confirmed']))->sum('allocated_amount');
    $outstanding   = max(0, round($invoiceTotal - $totalSettled, 2));
@endphp

<div class="card content-card mt-3">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <strong class="small"><i class="bi bi-receipt me-1 text-success"></i>Payment Receipts</strong>
        <div class="d-flex gap-3 small">
            <span class="text-muted">
                Invoiced: <span class="fw-semibold font-monospace">{{ $invoiceCurrency }} {{ number_format($invoiceTotal, 2) }}</span>
            </span>
            <span class="text-success">
                Settled: <span class="fw-semibold font-monospace">{{ number_format($totalSettled, 2) }}</span>
            </span>
            @if($outstanding > 0)
            <span class="text-danger">
                Outstanding: <span class="fw-semibold font-monospace">{{ number_format($outstanding, 2) }}</span>
            </span>
            @else
            <span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Fully Settled</span>
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        @if($settlements->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Receipt No</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Allocated Amount</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($settlements as $s)
                    <tr>
                        <td>
                            @if($s->receipt)
                            <a href="{{ route('finance.receipts.show', $s->receipt_id) }}"
                               class="fw-semibold font-monospace text-decoration-none">
                                {{ $s->receipt->receipt_no }}
                            </a>
                            @else
                            <span class="text-muted font-monospace">—</span>
                            @endif
                        </td>
                        <td class="text-muted">
                            {{ $s->receipt?->receipt_date ? \Carbon\Carbon::parse($s->receipt->receipt_date)->format('d M Y') : '—' }}
                        </td>
                        <td>
                            @php
                                $status = $s->receipt?->status ?? 'unknown';
                                $badge  = match($status) {
                                    'confirmed' => 'success',
                                    'draft'     => 'warning',
                                    'voided'    => 'danger',
                                    default     => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }} text-capitalize">{{ $status }}</span>
                        </td>
                        <td class="text-end font-monospace fw-semibold
                            {{ $s->receipt?->status === 'voided' ? 'text-decoration-line-through text-muted' : '' }}">
                            {{ number_format($s->allocated_amount, 2) }}
                        </td>
                        <td class="text-muted small">{{ $s->notes ?? '—' }}</td>
                        <td class="text-end">
                            @if($s->receipt)
                            <a href="{{ route('finance.receipts.show', $s->receipt_id) }}"
                               class="btn btn-outline-secondary btn-xs py-0 px-1 small">
                                <i class="bi bi-eye"></i>
                            </a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-3 small fst-italic">
            <i class="bi bi-inbox d-block fs-4 mb-1 opacity-25"></i>
            No receipts allocated against this invoice yet.
        </div>
        @endif
    </div>
    @can('finance.receipts.create')
    <div class="card-footer bg-transparent py-2 small text-muted">
        <i class="bi bi-info-circle me-1"></i>
        To record a payment, create a <a href="{{ route('finance.receipts.create') }}">Receipt</a>
        and allocate it to this invoice in the Allocations section.
    </div>
    @endcan
</div>
