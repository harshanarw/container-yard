{{--
    "Not posted to GL" banner + retry action.

    Params:
      $type    — invoice_type string ('general' | 'repair' | 'reefer' | 'storage'
                 | 'storage-handling'), matching InvoicePostingService::INVOICE_MODELS
      $invoice — the invoice model (needs ->id and ->status)

    Self-guarding: renders nothing unless the invoice is in a state where a GL
    posting is expected (issued/overdue/partially_paid/paid) AND no posted
    InvoicePosting exists for it. So a draft never shows a false warning.
--}}
@php
    $needsPosting = in_array($invoice->status, ['issued', 'overdue', 'partially_paid', 'paid'], true);
    $posting = $needsPosting
        ? \App\Models\InvoicePosting::where('invoice_type', $type)
            ->where('invoice_id', $invoice->id)->first()
        : null;
    $isPosted = $posting && $posting->status === 'posted';
@endphp

@if($needsPosting && ! $isPosted)
    <div class="alert alert-warning d-flex justify-content-between align-items-start gap-3">
        <div>
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Not posted to the general ledger.</strong>
            <div class="small mb-0 mt-1">
                @if($posting?->error_message)
                    {{ $posting->error_message }}
                @else
                    This issued invoice has no ledger entry yet.
                @endif
            </div>
        </div>
        <form method="POST"
              action="{{ route('billing.postings.retry', ['type' => $type, 'id' => $invoice->id]) }}"
              class="flex-shrink-0">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm btn-warning">
                <i class="bi bi-arrow-repeat me-1"></i>Retry posting
            </button>
        </form>
    </div>
@endif
