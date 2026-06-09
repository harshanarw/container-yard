@extends('layouts.app')

@section('title', 'Capture Status')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('guard-post.index') }}" class="text-decoration-none">Guard Post</a></li>
    <li class="breadcrumb-item active">Status</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-shield-check me-2 text-success"></i>Capture Status</h4>
        <p class="text-muted mb-0 small font-monospace">{{ $capture->reference_no }}</p>
    </div>
    <a href="{{ route('guard-post.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

{{-- Status panel --}}
<div class="card content-card mb-4" id="statusCard">
    @php
        $stateClass = match($capture->status) {
            'pending'  => 'border-warning',
            'cleared'  => 'border-success',
            'hold'     => 'border-warning',
            'rejected' => 'border-danger',
            default    => '',
        };
        $stateIcon = match($capture->status) {
            'pending'  => 'bi-hourglass-split text-warning',
            'cleared'  => 'bi-check-circle-fill text-success',
            'hold'     => 'bi-pause-circle-fill text-warning',
            'rejected' => 'bi-x-circle-fill text-danger',
            default    => 'bi-question-circle text-muted',
        };
        $stateText = match($capture->status) {
            'pending'  => 'Awaiting Review',
            'cleared'  => 'Cleared to Proceed',
            'hold'     => 'On Hold — Await Instructions',
            'rejected' => 'Entry Rejected',
            default    => ucfirst($capture->status),
        };
    @endphp
    <div class="card-body text-center py-5 {{ $stateClass }}" id="statusBody">
        <i class="bi {{ $stateIcon }} display-3 d-block mb-3" id="statusIcon"></i>
        <h3 class="fw-bold mb-1" id="statusText">{{ $stateText }}</h3>
        <p class="text-muted mb-0" id="statusSub">
            @if($capture->isPending())
                Your capture has been submitted and is waiting for review by the gate officer.
                This page will update automatically.
            @elseif($capture->isCleared())
                Cleared by {{ $capture->clearedBy?->full_name ?? 'Gate Officer' }}
                @if($capture->cleared_at) at {{ $capture->cleared_at->format('d M Y H:i') }} @endif
            @elseif($capture->isOnHold())
                Please wait at the gate for further instructions.
            @else
                Entry has been rejected. Please contact the gate officer.
            @endif
        </p>
        @if($capture->notes)
        <div class="mt-3 alert alert-light d-inline-block" style="max-width:400px;">
            <i class="bi bi-chat-square-text me-1"></i>{{ $capture->notes }}
        </div>
        @endif
    </div>
</div>

{{-- Direction badge --}}
<div class="mb-3 d-flex gap-2 align-items-center">
    @if($capture->direction === 'gate_in')
        <span class="badge bg-primary-subtle text-primary fs-6"><i class="bi bi-box-arrow-in-right me-1"></i>Gate-In</span>
    @else
        <span class="badge bg-success-subtle text-success fs-6"><i class="bi bi-box-arrow-right me-1"></i>Gate-Out</span>
    @endif
    <span class="text-muted small">Captured {{ $capture->captured_at?->format('d M Y H:i') }}</span>
</div>

{{-- Photos grid --}}
@php
    $photos = [
        ['label' => 'Container', 'url' => $capture->container_image_url],
        ['label' => 'Plate',     'url' => $capture->plate_image_url],
        ['label' => 'NIC Front', 'url' => $capture->nic_front_url],
        ['label' => 'NIC Back',  'url' => $capture->nic_back_url],
        ['label' => 'Licence',   'url' => $capture->license_front_url],
    ];
    $photos = array_filter($photos, fn($p) => $p['url']);
@endphp
@if($photos)
<div class="card content-card mb-4">
    <div class="card-header py-2"><i class="bi bi-images me-2"></i>Captured Photos</div>
    <div class="card-body">
        <div class="row g-2">
            @foreach($photos as $p)
            <div class="col-6 col-md-3">
                <a href="{{ $p['url'] }}" target="_blank">
                    <img src="{{ $p['url'] }}" alt="{{ $p['label'] }}"
                         class="img-fluid rounded border" style="max-height:140px;width:100%;object-fit:cover;">
                </a>
                <div class="text-center small text-muted mt-1">{{ $p['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Captured data summary --}}
<div class="card content-card">
    <div class="card-header py-2"><i class="bi bi-list-ul me-2"></i>Capture Details</div>
    <div class="card-body">
        <div class="row g-2 small">
            @if($capture->container_number)
            <div class="col-6 col-md-3"><span class="text-muted">Container No.</span><br><strong class="font-monospace">{{ $capture->container_number }}</strong></div>
            @endif
            @if($capture->iso_code)
            <div class="col-6 col-md-3"><span class="text-muted">ISO Code</span><br><strong>{{ $capture->iso_code }}</strong></div>
            @endif
            @if($capture->vehicle_number)
            <div class="col-6 col-md-3"><span class="text-muted">Vehicle No.</span><br><strong>{{ $capture->vehicle_number }}</strong></div>
            @endif
            @if($capture->vehicle_type)
            <div class="col-6 col-md-3"><span class="text-muted">Vehicle Type</span><br><strong>{{ $capture->vehicle_type }}</strong></div>
            @endif
            @if($capture->driver_name)
            <div class="col-6 col-md-3"><span class="text-muted">Driver Name</span><br><strong>{{ $capture->driver_name }}</strong></div>
            @endif
            @if($capture->nic_number)
            <div class="col-6 col-md-3"><span class="text-muted">NIC / ID</span><br><strong>{{ $capture->nic_number }}</strong></div>
            @endif
            @if($capture->driver_phone)
            <div class="col-6 col-md-3"><span class="text-muted">Phone</span><br><strong>{{ $capture->driver_phone }}</strong></div>
            @endif
        </div>
    </div>
</div>

@if($capture->isPending())
<div class="text-center text-muted small mt-3">
    <i class="bi bi-arrow-repeat me-1"></i>
    <span id="pollCountdown">Checking for updates in 10 s…</span>
</div>
@endif

@endsection

@if($capture->isPending())
@push('scripts')
<script>
(function () {
    const url    = @json(route('guard-post.status-json', $capture));
    let   secs   = 10;
    const cd     = document.getElementById('pollCountdown');

    const timer = setInterval(() => {
        secs--;
        if (cd) cd.textContent = `Checking for updates in ${secs} s…`;
        if (secs > 0) return;
        secs = 10;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'pending') {
                    // Reload to show final state
                    clearInterval(timer);
                    window.location.reload();
                }
            })
            .catch(() => {});
    }, 1000);
})();
</script>
@endpush
@endif
