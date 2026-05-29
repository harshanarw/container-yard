@extends('portal.layout')

@section('title', 'Estimate ' . $estimate->estimate_no)

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

<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
  <div>
    <h4 class="mb-1 fw-bold"><i class="bi bi-tools me-2 text-primary"></i>{{ $estimate->estimate_no }}</h4>
    <p class="text-muted mb-0 small">
      Container: <strong>{{ $estimate->container_no }}</strong>
      &nbsp;·&nbsp;
      <span class="badge bg-{{ $statusColors[$estimate->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $estimate->status)) }}</span>
    </p>
  </div>
  <a href="{{ route('estimates.pdf', $estimate) }}" class="btn btn-outline-secondary btn-sm" target="_blank">
    <i class="bi bi-file-pdf me-1"></i>Download PDF
  </a>
</div>

@if(!$portalToken->isValid())
<div class="alert alert-danger">
  <i class="bi bi-exclamation-triangle me-2"></i>This link has expired or been revoked.
  Please contact the depot for a new link.
</div>
@endif

@if($portalToken->expires_at && $portalToken->isValid())
<div class="alert alert-warning py-2 small">
  <i class="bi bi-clock me-1"></i>This review link expires on <strong>{{ $portalToken->expires_at->format('d M Y, H:i') }}</strong> UTC
</div>
@endif

@if(in_array($estimate->status, ['approved', 'rejected', 'partially_approved']))
<div class="alert alert-{{ $statusColors[$estimate->status] ?? 'secondary' }} py-2 small">
  <i class="bi bi-check-circle me-1"></i>You have already responded to this estimate
  (<strong>{{ ucfirst(str_replace('_', ' ', $estimate->status)) }}</strong>).
  Contact the depot if you need to revise your response.
</div>
@endif

<div class="row g-3">
  <!-- Left: Line Items -->
  <div class="col-lg-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">
        <i class="bi bi-list-ul me-2 text-primary"></i>Repair Line Items
        @if($canAct)
          <span class="badge bg-info ms-2 float-end">Review each line or use bulk actions below</span>
        @endif
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">#</th>
                <th>Description</th>
                <th class="text-end">Qty</th>
                <th class="text-end">Unit Price</th>
                <th class="text-end pe-3">Amount</th>
                <th class="text-center">Status</th>
                @if($canAct)<th class="text-center">Action</th>@endif
              </tr>
            </thead>
            <tbody>
              @foreach($estimate->lineItems as $i => $line)
              <tr>
                <td class="ps-3 text-muted small">{{ $i + 1 }}</td>
                <td class="small">
                  {{ $line->component }}
                  @if($line->repair_type)
                    <span class="text-muted">— {{ ucfirst(str_replace('_', ' ', $line->repair_type)) }}</span>
                  @endif
                </td>
                <td class="text-end small">{{ $line->qty }}</td>
                <td class="text-end small">{{ number_format($line->unit_price, 2) }}</td>
                <td class="text-end small pe-3 fw-semibold">
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
                    <form method="POST" action="{{ route('portal.estimate.line-action', ['token' => $token, 'lineItem' => $line->id]) }}">
                      @csrf
                      <input type="hidden" name="action" value="approved">
                      <button type="submit" class="btn btn-success btn-sm py-0 px-2" title="Approve">
                        <i class="bi bi-check-lg"></i>
                      </button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm py-0 px-2" title="Reject"
                            data-bs-toggle="modal" data-bs-target="#rejectLineModal{{ $line->id }}">
                      <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="button" class="btn btn-warning btn-sm py-0 px-2" title="Amend"
                            data-bs-toggle="modal" data-bs-target="#amendLineModal{{ $line->id }}">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </div>
                  @else
                    <span class="text-muted small">—</span>
                  @endif
                </td>
                @endif
              </tr>
              @endforeach
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td colspan="{{ $canAct ? 7 : 6 }}" class="text-end fw-semibold pe-3 pt-2">
                  Subtotal: {{ $estimate->currency }} {{ number_format($estimate->subtotal, 2) }}
                </td>
              </tr>
              @if($estimate->tax_percentage > 0)
              <tr>
                <td colspan="{{ $canAct ? 7 : 6 }}" class="text-end fw-semibold pe-3">
                  Tax ({{ $estimate->tax_percentage }}%): {{ $estimate->currency }} {{ number_format($estimate->tax_amount, 2) }}
                </td>
              </tr>
              @endif
              <tr class="table-primary">
                <td colspan="{{ $canAct ? 7 : 6 }}" class="text-end fw-bold pe-3 fs-6">
                  TOTAL: {{ $estimate->currency }} {{ number_format($estimate->grand_total, 2) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    @if($estimate->scope_of_work || $estimate->terms)
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><i class="bi bi-file-text me-2"></i>Scope &amp; Terms</div>
      <div class="card-body small">
        @if($estimate->scope_of_work)
          <div class="mb-2"><strong>Scope of Work</strong><br><span style="white-space:pre-line">{{ $estimate->scope_of_work }}</span></div>
        @endif
        @if($estimate->terms)
          <div><strong>Terms &amp; Conditions</strong><br><span style="white-space:pre-line">{{ $estimate->terms }}</span></div>
        @endif
      </div>
    </div>
    @endif
  </div>

  <!-- Right: Bulk Actions -->
  <div class="col-lg-4">
    @if($canAct)
    <div class="card shadow-sm mb-3 border-success">
      <div class="card-header bg-success text-white fw-semibold">
        <i class="bi bi-check-all me-2"></i>Approve All Lines
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('portal.estimate.approve', $token) }}">
          @csrf
          <div class="mb-3">
            <label class="form-label small fw-semibold">Notes (optional)</label>
            <textarea name="notes" class="form-control form-control-sm" rows="3"
                      placeholder="Any comments for the depot…"></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100"
                  onclick="return confirm('Approve all lines in this estimate?')">
            <i class="bi bi-check-circle me-1"></i>Approve Entire Estimate
          </button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm mb-3 border-danger">
      <div class="card-header bg-danger text-white fw-semibold">
        <i class="bi bi-x-circle me-2"></i>Reject Estimate
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('portal.estimate.reject', $token) }}">
          @csrf
          <div class="mb-3">
            <label class="form-label small fw-semibold">Reason <span class="text-danger">*</span></label>
            <textarea name="notes" class="form-control form-control-sm" rows="3"
                      placeholder="Reason for rejection…" required></textarea>
          </div>
          <button type="submit" class="btn btn-danger w-100"
                  onclick="return confirm('Reject this entire estimate?')">
            <i class="bi bi-x-circle me-1"></i>Reject Entire Estimate
          </button>
        </form>
      </div>
    </div>
    @endif

    <!-- Estimate Info -->
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold"><i class="bi bi-info-circle me-2"></i>Estimate Info</div>
      <div class="card-body small">
        <div class="mb-1"><span class="text-muted">Issue Date:</span> {{ $estimate->estimate_date->format('d M Y') }}</div>
        <div class="mb-1"><span class="text-muted">Valid Until:</span>
          <strong class="{{ $estimate->valid_until->isPast() ? 'text-danger' : '' }}">
            {{ $estimate->valid_until->format('d M Y') }}
          </strong>
        </div>
        <div class="mb-1"><span class="text-muted">Currency:</span> {{ $estimate->currency }}</div>
        @if($estimate->customer)
        <div class="mb-1"><span class="text-muted">Customer:</span> {{ $estimate->customer->name }}</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Per-line reject modals --}}
@if($canAct)
@foreach($estimate->lineItems as $line)
@if(($line->approval_status ?? 'pending') === 'pending')

<div class="modal fade" id="rejectLineModal{{ $line->id }}" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="{{ route('portal.estimate.line-action', ['token' => $token, 'lineItem' => $line->id]) }}">
        @csrf
        <input type="hidden" name="action" value="rejected">
        <div class="modal-header py-2">
          <h6 class="modal-title">Reject Line #{{ $loop->iteration }}</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <p class="small text-muted mb-2">{{ $line->component }}</p>
          <label class="form-label small fw-semibold">Reason</label>
          <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Optional reason…"></textarea>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-danger">Reject Line</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="amendLineModal{{ $line->id }}" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" action="{{ route('portal.estimate.line-action', ['token' => $token, 'lineItem' => $line->id]) }}">
        @csrf
        <input type="hidden" name="action" value="amended">
        <div class="modal-header py-2">
          <h6 class="modal-title">Request Amendment — Line #{{ $loop->iteration }}</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body py-2">
          <p class="small text-muted mb-2">{{ $line->component }}</p>
          <label class="form-label small fw-semibold">Amendment Notes <span class="text-danger">*</span></label>
          <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Describe requested changes…" required></textarea>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-warning">Request Amendment</button>
        </div>
      </form>
    </div>
  </div>
</div>

@endif
@endforeach
@endif

@endsection
