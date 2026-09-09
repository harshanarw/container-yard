@extends('layouts.app')
@section('title', 'Amend Plug Times')

@php
    $window   = $session->visitWindow();
    $from     = $window['from'];
    $to       = $window['to'];
    $wantsOut = $session->amendsPlugOut();

    // Ceilings the browser can enforce, so the operator is stopped while typing
    // rather than after submitting. The server repeats every one of these.
    $inMax  = $to ?: now();
    $outMax = $to ?: now();
@endphp

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('yard.reefer.show', $session) }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-pencil-square text-primary me-2"></i>Amend Plug Times</h4>
        <p class="text-muted small mb-0">
            <span class="font-monospace">{{ $session->container?->container_no }}</span>
            &nbsp;·&nbsp; {{ $session->customer?->name }}
            &nbsp;·&nbsp; <span class="badge {{ $session->status_badge_class }}">{{ $session->status_label }}</span>
        </p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">

        @if($session->isNotPlugged())
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div class="small">
                <strong>This container left without a plug-in being recorded.</strong>
                Entering both times below records that it was on power, moves the session to
                Completed, and puts it in front of the next electricity invoice. Enter the
                times you can actually establish - if the box genuinely ran unplugged, leave
                this session alone.
            </div>
        </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-clock-history me-2"></i>Recorded Times
            </div>
            <div class="card-body">

                {{-- The window an amendment has to fall inside, stated before typing. --}}
                <div class="alert alert-light border mb-4">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted d-block">Container arrived</span>
                            <span class="fw-medium">{{ $from?->format('d M Y H:i') ?? 'no arrival on record' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Container left</span>
                            <span class="fw-medium">{{ $to?->format('d M Y H:i') ?? 'still in the yard' }}</span>
                        </div>
                        <div class="col-12 text-muted border-top pt-2 mt-1">
                            A reefer cannot be plugged in before it arrives, or draw power after it
                            leaves. Times outside this range are rejected.
                        </div>
                    </div>
                </div>

                <div class="row g-2 small mb-4">
                    <div class="col-6">
                        <span class="text-muted d-block">Plug-in currently</span>
                        <span class="fw-medium">{{ $session->plug_in_at?->format('d M Y H:i') ?? 'not recorded' }}</span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">Plug-out currently</span>
                        <span class="fw-medium">{{ $session->plug_out_at?->format('d M Y H:i') ?? 'not recorded' }}</span>
                    </div>
                </div>

                <form action="{{ route('yard.reefer.store-amend', $session) }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Plug-In <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="plug_in_at" id="plug_in_at" required
                                   class="form-control @error('plug_in_at') is-invalid @enderror"
                                   @if($from) min="{{ $from->format('Y-m-d\TH:i') }}" @endif
                                   max="{{ $inMax->format('Y-m-d\TH:i') }}"
                                   value="{{ old('plug_in_at', $session->plug_in_at?->format('Y-m-d\TH:i')) }}">
                            @error('plug_in_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        @if($wantsOut)
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Plug-Out <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="plug_out_at" id="plug_out_at" required
                                   class="form-control @error('plug_out_at') is-invalid @enderror"
                                   @if($from) min="{{ $from->format('Y-m-d\TH:i') }}" @endif
                                   max="{{ $outMax->format('Y-m-d\TH:i') }}"
                                   value="{{ old('plug_out_at', $session->plug_out_at?->format('Y-m-d\TH:i')) }}">
                            @error('plug_out_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @else
                        <div class="col-md-6">
                            <label class="form-label text-muted">Plug-Out</label>
                            <input type="text" class="form-control" disabled value="Session is still active">
                            <div class="form-text">Use Record Plug-Out when the box comes off power.</div>
                        </div>
                        @endif
                    </div>

                    @if($wantsOut && $from && $to)
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="fillWholeVisit">
                            <i class="bi bi-arrows-expand-vertical me-1"></i>Plugged for the whole visit
                        </button>
                        <div class="form-text">
                            Fills the arrival and departure times above. That is an assumption, not a
                            record - check it before saving, and say so in the reason.
                        </div>
                    </div>
                    @endif

                    <div class="mt-4">
                        <label class="form-label fw-medium">Reason for the amendment <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="2" required
                                  class="form-control @error('reason') is-invalid @enderror"
                                  placeholder="e.g. Plug-in was not recorded at the time; times taken from the reefer log sheet.">{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            Stored in the audit trail alongside the old and new values, so whoever
                            reads this later knows why the charge changed.
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-check2 me-1"></i>Save Amendment
                        </button>
                        <a href="{{ route('yard.reefer.show', $session) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($wantsOut && $from && $to)
@push('scripts')
<script>
document.getElementById('fillWholeVisit')?.addEventListener('click', function () {
    // Fills the form, deliberately without submitting it: the operator still
    // confirms, and still has to say in the reason that this was an assumption.
    document.getElementById('plug_in_at').value  = @json($from->format('Y-m-d\TH:i'));
    document.getElementById('plug_out_at').value = @json($to->format('Y-m-d\TH:i'));
});
</script>
@endpush
@endif
@endsection
