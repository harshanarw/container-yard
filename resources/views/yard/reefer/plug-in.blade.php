@extends('layouts.app')
@section('title', 'Record Plug-In')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('yard.reefer.index') }}" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-plug-fill text-success me-2"></i>Record Reefer Plug-In</h4>
        <p class="text-muted small mb-0">{{ $session->container?->container_no }} &mdash; {{ $session->customer?->name }}</p>
    </div>
</div>


<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm border-success">
            <div class="card-header bg-success-subtle text-success fw-semibold">
                <i class="bi bi-plug-fill me-2"></i>Plug-In Details
            </div>
            <div class="card-body">
                {{-- Container info --}}
                <div class="alert alert-light border mb-4">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-muted d-block">Container No</span>
                            <span class="font-monospace fw-bold">{{ $session->container?->container_no }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Equipment Type</span>
                            <span>{{ $session->container?->equipmentType?->dropdown_label ?? '—' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Customer</span>
                            <span>{{ $session->customer?->name }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Gate In</span>
                            <span>{{ $session->gateMovement?->gate_in_time?->format('d M Y H:i') ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <form action="{{ route('yard.reefer.store-plug-in', $session) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-medium">Plug-In Date &amp; Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="plug_in_at" class="form-control @error('plug_in_at') is-invalid @enderror"
                               value="{{ old('plug_in_at', now()->format('Y-m-d\TH:i')) }}" required>
                        @error('plug_in_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Reefer Service Type <span class="text-danger">*</span></label>
                        <select name="service_type" class="form-select @error('service_type') is-invalid @enderror" required>
                            <option value="">— Select Service Type —</option>
                            @foreach(\App\Models\ReeferElectricityTariff::SERVICE_TYPES as $val => $label)
                                <option value="{{ $val }}" @selected(old('service_type', $session->service_type) === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Short-Term PTI is billed hourly (usually USD); Long-Term electricity is billed daily (usually LKR).</div>
                        @error('service_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Set Temperature (°C)</label>
                        <input type="number" name="set_temperature" class="form-control @error('set_temperature') is-invalid @enderror"
                               step="0.1" min="-50" max="40" placeholder="e.g. -18"
                               value="{{ old('set_temperature') }}">
                        <div class="form-text">Optional — operator-requested temperature set-point.</div>
                        @error('set_temperature')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any observations...">{{ old('notes') }}</textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1">
                            <i class="bi bi-plug-fill me-1"></i>Confirm Plug-In
                        </button>
                        <a href="{{ route('yard.reefer.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
