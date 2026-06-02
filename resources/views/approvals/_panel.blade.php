{{--
  Approval status panel for any approvable document.
  Required variables: $approvalRequest (can be null), $movement (GateMovement)
--}}
@php
    $req        = $approvalRequest ?? null;
    $canInitiate = !$req || $req->isCancelled() || $req->isRejected();
    $canCancel  = $req && $req->isPending() &&
                  (auth()->user()?->id === $req->initiated_by ||
                   in_array(auth()->user()?->role, ['system_administrator','administrator']));
    $nextAction = $req?->nextPendingAction();
    $canAction  = $req && $nextAction && app(\App\Services\ApprovalService::class)->canAction($req, auth()->user());
@endphp

<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between
        @if(!$req) bg-secondary
        @elseif($req->isPending()) bg-warning
        @elseif($req->isApproved()) bg-success
        @elseif($req->isRejected()) bg-danger
        @else bg-secondary
        @endif text-white">
        <span><i class="bi bi-check2-circle me-2"></i>Digital Approval</span>
        @if(!$req || $canInitiate)
            <form method="POST" action="{{ route('approvals.gate-pass.initiate', $movement) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-light fw-semibold"
                        onclick="return confirm('Submit this gate pass for digital approval?')">
                    <i class="bi bi-send me-1"></i>Submit for Approval
                </button>
            </form>
        @elseif($req->isPending() && $canCancel)
            <button type="button" class="btn btn-sm btn-outline-light"
                    data-bs-toggle="modal" data-bs-target="#cancelApprovalModal">
                <i class="bi bi-x-circle me-1"></i>Cancel Request
            </button>
        @endif
    </div>

    <div class="card-body p-3">

        @if(!$req)
        <p class="text-muted small mb-0">
            <i class="bi bi-info-circle me-1"></i>
            No approval request has been submitted for this gate pass yet.
            Submit for approval to route it through the digital workflow.
        </p>

        @else

        {{-- Status badge --}}
        <div class="mb-3 d-flex align-items-center gap-2">
            @if($req->isPending())
                <span class="badge bg-warning text-dark fs-6"><i class="bi bi-hourglass-split me-1"></i>Pending Approval</span>
            @elseif($req->isApproved())
                <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Approved</span>
            @elseif($req->isRejected())
                <span class="badge bg-danger fs-6"><i class="bi bi-x-circle me-1"></i>Rejected</span>
            @elseif($req->isCancelled())
                <span class="badge bg-secondary fs-6"><i class="bi bi-slash-circle me-1"></i>Cancelled</span>
            @endif
            <span class="small text-muted">Request #{{ $req->id }} &nbsp;·&nbsp; By {{ $req->initiatedBy?->name }} &nbsp;·&nbsp; {{ $req->initiated_at?->format('d M Y H:i') }}</span>
        </div>

        {{-- Steps timeline --}}
        <div class="d-flex flex-column gap-2">
            @foreach($req->actions as $action)
            @php
                $isNext = $req->isPending() && $action->isPending() && $action->step_order === $nextAction?->step_order;
            @endphp
            <div class="d-flex align-items-start gap-3 p-2 rounded
                @if($action->isApproved()) bg-success-subtle
                @elseif($action->isRejected()) bg-danger-subtle
                @elseif($isNext) bg-warning-subtle border border-warning
                @else bg-light
                @endif">

                <div class="flex-shrink-0 mt-1">
                    @if($action->isApproved())
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    @elseif($action->isRejected())
                        <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                    @elseif($isNext)
                        <i class="bi bi-hourglass-split text-warning fs-5"></i>
                    @else
                        <i class="bi bi-circle text-secondary fs-5"></i>
                    @endif
                </div>

                <div class="flex-grow-1">
                    <div class="fw-semibold small">{{ $action->step_label }}</div>
                    @if($action->actioned_at)
                    <div class="small text-muted">
                        {{ $action->actionedBy?->name ?? '—' }} &nbsp;·&nbsp; {{ $action->actioned_at?->format('d M Y H:i') }}
                    </div>
                    @endif
                    @if($action->remarks)
                    <div class="small text-muted fst-italic mt-1">{{ $action->remarks }}</div>
                    @endif
                </div>

                {{-- Inline action buttons for the next pending step --}}
                @if($isNext && $canAction)
                <div class="d-flex gap-2 flex-shrink-0">
                    <form method="POST" action="{{ route('approvals.actions.approve', $action) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm"
                                onclick="return confirm('Approve this step?')">
                            <i class="bi bi-check-lg me-1"></i>Approve
                        </button>
                    </form>
                    <button type="button" class="btn btn-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#rejectStepModal">
                        <i class="bi bi-x-lg me-1"></i>Reject
                    </button>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Cancellation note --}}
        @if($req->isCancelled())
        <div class="mt-2 small text-muted">
            Cancelled by {{ $req->cancelledBy?->name }} on {{ $req->cancelled_at?->format('d M Y H:i') }}.
            @if($req->cancellation_reason)
            Reason: {{ $req->cancellation_reason }}
            @endif
        </div>
        @endif

        @endif
    </div>
</div>

{{-- Cancel Modal --}}
@if($req && $canCancel)
<div class="modal fade" id="cancelApprovalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-slash-circle me-2"></i>Cancel Approval Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('approvals.requests.cancel', $req) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">Cancelling this request will stop the approval workflow. You can re-submit later.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required
                                  placeholder="State why you are cancelling…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-secondary">Cancel Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Reject Step Modal --}}
@if($nextAction && $canAction)
<div class="modal fade" id="rejectStepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Reject Step</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('approvals.actions.reject', $nextAction) }}">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">Rejecting <strong>{{ $nextAction->step_label }}</strong> will mark the entire request as rejected.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea name="remarks" class="form-control" rows="3" required
                                  placeholder="State the reason for rejection…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
