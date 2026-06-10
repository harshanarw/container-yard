@extends('layouts.app')
@section('title', 'Record Plug-Out')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('yard.reefer.index') }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-plug text-danger me-2"></i>Record Reefer Plug-Out</h4>
        <p class="text-muted small mb-0">{{ $session->container?->container_no }} &mdash; {{ $session->customer?->name }}</p>
    </div>
</div>


<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-danger">
            <div class="card-header bg-danger-subtle text-danger fw-semibold">
                <i class="bi bi-plug me-2"></i>Plug-Out Details
            </div>
            <div class="card-body">
                {{-- Session summary --}}
                <div class="alert alert-light border mb-4">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted d-block">Container No</span>
                            <span class="font-monospace fw-bold">{{ $session->container?->container_no }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Customer</span>
                            <span>{{ $session->customer?->name }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Plugged In</span>
                            <span>{{ $session->plug_in_at?->format('d M Y H:i') ?? '—' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Duration so far</span>
                            @php $hrs = round($session->totalHours(), 1); @endphp
                            <span>{{ $hrs }}h ({{ $session->totalDays() }} day{{ $session->totalDays() != 1 ? 's' : '' }})</span>
                        </div>
                        @if($session->set_temperature)
                        <div class="col-6">
                            <span class="text-muted d-block">Set Temperature</span>
                            <span>{{ $session->set_temperature }}°C</span>
                        </div>
                        @endif
                    </div>
                </div>

                <form action="{{ route('yard.reefer.store-plug-out', $session) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-medium">Plug-Out Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="plug_out_at" class="form-control @error('plug_out_at') is-invalid @enderror"
                               value="{{ old('plug_out_at', now()->format('Y-m-d\TH:i')) }}" required>
                        @error('plug_out_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any observations...">{{ old('notes') }}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger flex-grow-1">
                            <i class="bi bi-plug me-1"></i>Confirm Plug-Out
                        </button>
                        <a href="{{ route('yard.reefer.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
