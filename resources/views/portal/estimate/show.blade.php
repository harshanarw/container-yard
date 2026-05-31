@extends('portal.layout')

@section('title', 'Estimate ' . $estimate->estimate_no)

@push('head')
<style>
  .code-chip {
    display: inline-block; font-size: .7rem; font-weight: 700; letter-spacing: .4px;
    padding: 1px 7px; border-radius: 20px; font-family: monospace; white-space: nowrap;
  }
  .code-chip.blue    { background: #dbeafe; color: #1d4ed8; }
  .code-chip.green   { background: #d1fae5; color: #065f46; }
  .code-chip.orange  { background: #fef3c7; color: #92400e; }
  .summary-card { border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
  .summary-header { background: linear-gradient(135deg, #1a56db 0%, #0d6efd 100%); padding: 24px 28px; color: #fff; }
  .summary-meta { display: flex; gap: 24px; flex-wrap: wrap; margin-top: 14px; }
  .summary-meta-item label { font-size: .72rem; opacity: .75; display: block; margin-bottom: 2px; text-transform: uppercase; letter-spacing: .5px; }
  .summary-meta-item span  { font-size: .92rem; font-weight: 600; }
  .total-pill { background: rgba(255,255,255,.18); border-radius: 10px; padding: 10px 20px; text-align: center; }
  .total-pill .label { font-size: .72rem; opacity: .8; text-transform: uppercase; letter-spacing: .5px; }
  .total-pill .value { font-size: 1.5rem; font-weight: 800; line-height: 1.2; }
  .action-bar { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.07); padding: 20px 24px; margin-bottom: 16px; }
  .tfoot-row td { font-size: .85rem; padding: 5px 10px; white-space: nowrap; }
  .tfoot-total td { font-size: .95rem; font-weight: 700; background: #eff6ff; color: #1a56db; padding: 10px 10px; border-top: 2px solid #1a56db !important; white-space: nowrap; }
  .col-hide-mobile { /* hidden below md */ }
  @media (max-width: 767px) {
    .col-hide-mobile { display: none !important; }
    .tfoot-row td:first-child, .tfoot-total td:first-child { display: none; }
    .summary-meta { gap: 14px; }
    .summary-header { padding: 18px 16px; }
    .total-pill .value { font-size: 1.25rem; }
  }
</style>
@endpush

@section('content')
@php
  $statusColors = [
    'draft'              => 'secondary',
    'sent'               => 'info',
    'approved'           => 'success',
    'rejected'           => 'danger',
    'partially_approved' => 'warning',
    'under_review'       => 'primary',
    'returned'           => 'dark',
  ];
  $lineStatusColors = [
    'pending'  => 'secondary',
    'approved' => 'success',
    'rejected' => 'danger',
    'amended'  => 'warning',
  ];
  $canAct = in_array($estimate->status, ['sent', 'under_review']) && $portalToken->isValid();
@endphp

{{-- ── Alerts ── --}}
@if(!$portalToken->isValid())
<div class="alert alert-danger mt-3">
  <i class="bi bi-exclamation-triangle me-2"></i>This link has expired or been revoked.
  Please contact the depot for a new link.
</div>
@endif

@if($portalToken->expires_at && $portalToken->isValid())
<div class="alert alert-warning py-2 small mt-3">
  <i class="bi bi-clock me-1"></i>Review link expires on
  <strong>{{ $portalToken->expires_at->format('d M Y, H:i') }} UTC</strong>
</div>
@endif

@if(in_array($estimate->status, ['approved', 'rejected', 'partially_approved']))
<div class="alert alert-{{ $statusColors[$estimate->status] ?? 'secondary' }} py-2 small mt-3">
  <i class="bi bi-check-circle me-1"></i>
  You have already responded to this estimate
  (<strong>{{ ucfirst(str_replace('_', ' ', $estimate->status)) }}</strong>).
  Contact the depot if you need to revise your response.
</div>
@endif

{{-- ── Summary header card ── --}}
<div class="card summary-card mb-4 mt-2">
  <div class="summary-header">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
      <div>
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-tools" style="font-size:1.2rem;opacity:.8"></i>
          <span style="font-size:.75rem;opacity:.75;text-transform:uppercase;letter-spacing:.8px;">Repair Estimate</span>
          <span class="badge bg-{{ $statusColors[$estimate->status] ?? 'secondary' }} ms-1">
            {{ ucfirst(str_replace('_', ' ', $estimate->status)) }}
          </span>
        </div>
        <div style="font-size:1.6rem;font-weight:800;letter-spacing:.5px;font-family:monospace;">
          {{ $estimate->estimate_no }}
        </div>
        @if($estimate->version_no > 1)
          <div style="font-size:.78rem;opacity:.75;">Version {{ $estimate->version_no }}</div>
        @endif
      </div>
      <div class="total-pill">
        <div class="label">Grand Total</div>
        <div class="value">{{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}</div>
        @if($estimate->sscl_amount > 0 || $estimate->vat_amount > 0)
          <div style="font-size:.72rem;opacity:.8;margin-top:2px;">
            incl. taxes
          </div>
        @endif
      </div>
    </div>

    <div class="summary-meta">
      <div class="summary-meta-item">
        <label>Container</label>
        <span>{{ $estimate->container_no }}</span>
      </div>
      @if($estimate->customer)
      <div class="summary-meta-item">
        <label>Customer</label>
        <span>{{ $estimate->customer->name }}</span>
      </div>
      @endif
      <div class="summary-meta-item">
        <label>Issue Date</label>
        <span>{{ $estimate->estimate_date->format('d M Y') }}</span>
      </div>
      <div class="summary-meta-item">
        <label>Valid Until</label>
        <span class="{{ $estimate->valid_until->isPast() ? 'text-warning' : '' }}">
          {{ $estimate->valid_until->format('d M Y') }}
          @if($estimate->valid_until->isPast())
            <i class="bi bi-exclamation-circle ms-1"></i>
          @endif
        </span>
      </div>
      <div class="ms-auto">
        <a href="{{ route('portal.estimate.pdf', $token) }}" class="btn btn-sm btn-light" target="_blank">
          <i class="bi bi-file-pdf me-1"></i>Download PDF
        </a>
      </div>
    </div>
  </div>
</div>

{{-- ── Line Items card ── --}}
<div class="card shadow-sm mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-semibold"><i class="bi bi-list-ul me-2 text-primary"></i>Repair Line Items</span>
    @if($canAct)
      <span class="badge bg-light text-secondary border small">Review each line, then use the actions below</span>
    @endif
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0 small">
        <thead class="table-light">
          <tr>
            <th class="ps-3" style="width:3%">#</th>
            <th class="col-hide-mobile" style="width:9%">MR Code</th>
            <th class="col-hide-mobile" style="width:10%">Charge Code</th>
            <th>Description</th>
            <th class="col-hide-mobile" style="width:10%">Repair Type</th>
            <th class="text-end col-hide-mobile" style="width:5%">Qty</th>
            <th class="text-end col-hide-mobile" style="width:9%">Unit Price</th>
            <th class="col-hide-mobile" style="width:7%">Tax Code</th>
            <th class="text-end pe-3" style="width:9%">Amount</th>
            <th class="text-center" style="width:8%">Status</th>
            @if($canAct)<th class="text-center" style="width:9%">Action</th>@endif
          </tr>
        </thead>
        <tbody>
          @foreach($estimate->lineItems as $i => $line)
          <tr class="{{ $loop->even ? 'table-light' : '' }}">
            <td class="ps-3 text-muted">{{ $i + 1 }}</td>

            {{-- MR Code chip --}}
            <td class="col-hide-mobile">
              @if($line->componentCode)
                <span class="code-chip blue" title="{{ $line->componentCode->name }}">
                  {{ $line->componentCode->code }}
                </span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Charge Code chip --}}
            <td class="col-hide-mobile">
              @if($line->chargeCode)
                <span class="code-chip green" title="{{ $line->chargeCode->name }}">
                  {{ $line->chargeCode->code }}
                </span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- Description --}}
            <td class="fw-semibold">{{ $line->component }}</td>

            {{-- Repair type --}}
            <td class="text-muted col-hide-mobile">{{ $line->repair_type ? ucfirst(str_replace('_', ' ', $line->repair_type)) : '—' }}</td>

            <td class="text-end col-hide-mobile">{{ number_format($line->qty, 2) }}</td>
            <td class="text-end col-hide-mobile">{{ number_format($line->unit_price, 2) }}</td>

            {{-- Tax Code --}}
            <td class="col-hide-mobile">
              @if($line->taxCode)
                <span class="code-chip orange" title="{{ $line->taxCode->name ?? $line->taxCode->code }}">
                  {{ $line->taxCode->code }}
                </span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            <td class="text-end pe-3 fw-semibold" style="white-space:nowrap;">
              {{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}
            </td>

            <td class="text-center">
              <span class="badge bg-{{ $lineStatusColors[$line->approval_status ?? 'pending'] ?? 'secondary' }}">
                {{ ucfirst($line->approval_status ?? 'pending') }}
              </span>
            </td>

            @if($canAct)
            <td class="text-center">
              @if(($line->approval_status ?? 'pending') === 'pending')
              <div class="d-flex gap-1 justify-content-center">
                <button type="button" class="btn btn-success btn-sm py-0 px-2" title="Approve"
                        data-bs-toggle="modal" data-bs-target="#approveLineModal"
                        data-action="{{ route('portal.estimate.line-action', ['token' => $token, 'lineItem' => $line->id]) }}"
                        data-label="{{ $line->component }}{{ $line->repair_type ? ' — ' . ucfirst(str_replace('_', ' ', $line->repair_type)) : '' }}"
                        data-amount="{{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}">
                  <i class="bi bi-check-lg"></i>
                </button>
                <button type="button" class="btn btn-danger btn-sm py-0 px-2" title="Reject"
                        data-bs-toggle="modal" data-bs-target="#rejectLineModal{{ $line->id }}">
                  <i class="bi bi-x-lg"></i>
                </button>
                <button type="button" class="btn btn-warning btn-sm py-0 px-2" title="Request Amendment"
                        data-bs-toggle="modal" data-bs-target="#amendLineModal{{ $line->id }}">
                  <i class="bi bi-pencil"></i>
                </button>
              </div>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            @endif
          </tr>
          @endforeach
        </tbody>

        {{-- ── Tax & Total footer ── --}}
        @php
          // total columns: # + MR + Charge + Desc + RepairType + Qty + UnitPrice + Tax + Amount + Status [+ Action]
          $totalCols    = $canAct ? 11 : 10;
          // Amount + Status [+ Action] stay visible; everything else is the label span
          $amountCols   = $canAct ? 3 : 2; // Amount + Status [+ Action]
          $labelCols    = $totalCols - $amountCols;
        @endphp
        <tfoot>
          <tr class="tfoot-row">
            <td colspan="{{ $labelCols }}" class="text-end text-muted border-top">Subtotal</td>
            <td class="text-end border-top" colspan="{{ $amountCols }}">
              {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
            </td>
          </tr>
          @if($estimate->sscl_amount > 0)
          <tr class="tfoot-row">
            <td colspan="{{ $labelCols }}" class="text-end text-muted">SSCL</td>
            <td class="text-end" colspan="{{ $amountCols }}">
              {{ $estimate->currency }} {{ number_format($estimate->sscl_amount, 2) }}
            </td>
          </tr>
          @endif
          @if($estimate->vat_amount > 0)
          <tr class="tfoot-row">
            <td colspan="{{ $labelCols }}" class="text-end text-muted">VAT</td>
            <td class="text-end" colspan="{{ $amountCols }}">
              {{ $estimate->currency }} {{ number_format($estimate->vat_amount, 2) }}
            </td>
          </tr>
          @endif
          <tr class="tfoot-total">
            <td colspan="{{ $labelCols }}" class="text-end">Grand Total</td>
            <td class="text-end" colspan="{{ $amountCols }}">
              {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

{{-- ── Scope & Terms ── --}}
@if($estimate->scope_of_work || $estimate->terms)
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold"><i class="bi bi-file-text me-2 text-secondary"></i>Scope &amp; Terms</div>
  <div class="card-body small">
    @if($estimate->scope_of_work)
      <p class="mb-1 fw-semibold">Scope of Work</p>
      <p class="text-muted mb-3" style="white-space:pre-line">{{ $estimate->scope_of_work }}</p>
    @endif
    @if($estimate->terms)
      <p class="mb-1 fw-semibold">Terms &amp; Conditions</p>
      <p class="text-muted mb-0" style="white-space:pre-line">{{ $estimate->terms }}</p>
    @endif
  </div>
</div>
@endif

{{-- ── Signed Approval Document ── --}}
@php
  $signedDoc = $estimate->documents()->where('document_type', 'signed_approval')->latest()->first();
@endphp
<div class="card shadow-sm mb-4">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span class="fw-semibold"><i class="bi bi-pen me-2 text-primary"></i>Signed Approval Document</span>
    <a href="{{ route('portal.estimate.approval-form', $token) }}" target="_blank"
       class="btn btn-sm btn-outline-primary">
      <i class="bi bi-printer me-1"></i>Download Approval Form
    </a>
  </div>
  <div class="card-body">

    @if($signedDoc)
    {{-- Already uploaded --}}
    <div class="d-flex align-items-center gap-3 p-3 bg-success-subtle border border-success rounded mb-3">
      <i class="bi bi-file-earmark-check text-success" style="font-size:2rem;flex-shrink:0;"></i>
      <div class="flex-grow-1 min-w-0">
        <div class="fw-semibold text-success small">Signed document uploaded</div>
        <div class="text-muted small text-truncate">{{ $signedDoc->original_name }}</div>
        <div class="text-muted" style="font-size:.72rem;">
          Uploaded {{ $signedDoc->created_at->format('d M Y H:i') }}
        </div>
      </div>
      <a href="{{ route('portal.estimate.signed-doc.view', [$token, $signedDoc->id]) }}"
         target="_blank" class="btn btn-sm btn-outline-success flex-shrink-0">
        <i class="bi bi-eye me-1"></i>View
      </a>
    </div>
    <p class="small text-muted mb-2">You can replace the document by uploading a new file below.</p>
    @else
    <p class="small text-muted mb-3">
      <strong>How it works:</strong> Download and print the Approval Form, sign it manually, then scan or photograph it and upload below.
      This provides a physical signed record of your approval.
    </p>
    @endif

    <form method="POST" action="{{ route('portal.estimate.upload-signed', $token) }}"
          enctype="multipart/form-data">
      @csrf
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <input type="file" name="signed_document" id="signed_document"
               class="form-control form-control-sm @error('signed_document') is-invalid @enderror"
               accept=".pdf,.jpg,.jpeg,.png" style="max-width:340px;">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-upload me-1"></i>{{ $signedDoc ? 'Replace Document' : 'Upload Signed Document' }}
        </button>
      </div>
      @error('signed_document')
        <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
      @enderror
      <div class="text-muted mt-1" style="font-size:.72rem;">Accepted: PDF, JPG, PNG &nbsp;·&nbsp; Max 10 MB</div>
    </form>

  </div>
</div>

{{-- ── Bulk Action cards ── --}}
@if($canAct)
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card h-100 border-success shadow-sm">
      <div class="card-header bg-success text-white fw-semibold">
        <i class="bi bi-check-all me-2"></i>Approve Entire Estimate
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted mb-3">Approving will mark all line items as accepted and notify the depot to proceed with repairs.</p>
        <button type="button" class="btn btn-success w-100 mt-auto"
                data-bs-toggle="modal" data-bs-target="#bulkApproveModal">
          <i class="bi bi-check-circle me-1"></i>Approve Entire Estimate
        </button>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100 border-danger shadow-sm">
      <div class="card-header bg-danger text-white fw-semibold">
        <i class="bi bi-x-circle me-2"></i>Reject Estimate
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted mb-3">Rejecting will decline all repair work. The depot will be notified and may resubmit a revised estimate.</p>
        <button type="button" class="btn btn-danger w-100 mt-auto"
                data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
          <i class="bi bi-x-circle me-1"></i>Reject Entire Estimate
        </button>
      </div>
    </div>
  </div>
</div>
@endif

{{-- ── Per-line modals ── --}}
@if($canAct)
@foreach($estimate->lineItems as $line)
@if(($line->approval_status ?? 'pending') === 'pending')

<div class="modal fade" id="rejectLineModal{{ $line->id }}" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('portal.estimate.line-action', ['token' => $token, 'lineItem' => $line->id]) }}">
        @csrf
        <input type="hidden" name="action" value="rejected">
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Reject Line #{{ $loop->iteration }}</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="bg-light rounded p-2 mb-3 small">
            <strong>{{ $line->component }}</strong>
            @if($line->repair_type) — {{ ucfirst(str_replace('_', ' ', $line->repair_type)) }}@endif
            <span class="float-end fw-semibold">{{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}</span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="approver_name" class="form-control form-control-sm" placeholder="Your full name" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Designation <span class="text-danger">*</span></label>
              <input type="text" name="approver_designation" class="form-control form-control-sm" placeholder="e.g. Operations Manager" required>
            </div>
          </div>
          <label class="form-label small fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></label>
          <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Describe why this line is being rejected…"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-x-circle me-1"></i>Reject Line</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="amendLineModal{{ $line->id }}" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('portal.estimate.line-action', ['token' => $token, 'lineItem' => $line->id]) }}">
        @csrf
        <input type="hidden" name="action" value="amended">
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-pencil text-warning me-2"></i>Request Amendment — Line #{{ $loop->iteration }}</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="bg-light rounded p-2 mb-3 small">
            <strong>{{ $line->component }}</strong>
            @if($line->repair_type) — {{ ucfirst(str_replace('_', ' ', $line->repair_type)) }}@endif
            <span class="float-end fw-semibold">{{ $estimate->currency }} {{ number_format($line->line_amount, 2) }}</span>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="approver_name" class="form-control form-control-sm" placeholder="Your full name" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Designation <span class="text-danger">*</span></label>
              <input type="text" name="approver_designation" class="form-control form-control-sm" placeholder="e.g. Operations Manager" required>
            </div>
          </div>
          <label class="form-label small fw-semibold">Amendment Notes <span class="text-danger">*</span></label>
          <textarea name="notes" class="form-control form-control-sm" rows="2"
                    placeholder="Describe the changes you are requesting…" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-warning"><i class="bi bi-pencil me-1"></i>Submit Amendment</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endif
@endforeach
@endif

@if($canAct)

{{-- ── Shared: Line Approve Modal ── --}}
<div class="modal fade" id="approveLineModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="approveLineForm">
        @csrf
        <input type="hidden" name="action" value="approved">
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-check-circle text-success me-2"></i>Approve Line Item</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="bg-light rounded p-2 mb-3 small" id="approveLineDetails"></div>
          <div class="alert alert-success py-2 small mb-3">
            <i class="bi bi-shield-check me-1"></i>
            Your name and designation will be recorded as the authorising signatory for this action.
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="approver_name" class="form-control form-control-sm" placeholder="Your full name" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Designation <span class="text-danger">*</span></label>
              <input type="text" name="approver_designation" class="form-control form-control-sm" placeholder="e.g. Operations Manager" required>
            </div>
          </div>
          <label class="form-label small fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
          <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Any comments…"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle me-1"></i>Confirm Approval</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Bulk Approve Modal ── --}}
<div class="modal fade" id="bulkApproveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('portal.estimate.approve', $token) }}">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-check-all text-success me-2"></i>Approve Entire Estimate</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-success py-2 small mb-3">
            <i class="bi bi-shield-check me-1"></i>
            By approving, you confirm that you are authorised to accept this estimate on behalf of your organisation.
            Your name, designation, and IP address will be recorded.
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="approver_name" class="form-control form-control-sm" placeholder="Your full name" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Designation <span class="text-danger">*</span></label>
              <input type="text" name="approver_designation" class="form-control form-control-sm" placeholder="e.g. Operations Manager" required>
            </div>
          </div>
          <label class="form-label small fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
          <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Any comments for the depot…"></textarea>
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="approveDeclaration" required>
            <label class="form-check-label small" for="approveDeclaration">
              I confirm I am authorised to approve this repair estimate on behalf of my organisation.
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Confirm Approval</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ── Bulk Reject Modal ── --}}
<div class="modal fade" id="bulkRejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('portal.estimate.reject', $token) }}">
        @csrf
        <div class="modal-header">
          <h6 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>Reject Entire Estimate</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-warning py-2 small mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Your name, designation, and IP address will be recorded as the authorising signatory for this rejection.
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="approver_name" class="form-control form-control-sm" placeholder="Your full name" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Designation <span class="text-danger">*</span></label>
              <input type="text" name="approver_designation" class="form-control form-control-sm" placeholder="e.g. Operations Manager" required>
            </div>
          </div>
          <label class="form-label small fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
          <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Reason for rejection…" required></textarea>
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" id="rejectDeclaration" required>
            <label class="form-check-label small" for="rejectDeclaration">
              I confirm I am authorised to reject this repair estimate on behalf of my organisation.
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Confirm Rejection</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endif

@endsection

@push('scripts')
<script>
(function () {
  const approveLineModal = document.getElementById('approveLineModal');
  if (!approveLineModal) return;

  approveLineModal.addEventListener('show.bs.modal', function (e) {
    const btn    = e.relatedTarget;
    const form   = document.getElementById('approveLineForm');
    form.action  = btn.dataset.action;
    document.getElementById('approveLineDetails').innerHTML =
      '<strong>' + btn.dataset.label + '</strong>' +
      '<span class="float-end fw-semibold">' + btn.dataset.amount + '</span>';
    // Reset fields each time modal opens
    form.querySelectorAll('input[type=text], textarea').forEach(function(el) { el.value = ''; });
  });
})();
</script>
@endpush
