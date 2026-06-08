@extends('layouts.app')

@section('title', 'Capture Status — ' . $capture->reference_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('guard-post.index') }}">Guard Post</a></li>
    <li class="breadcrumb-item active">{{ $capture->reference_no }}</li>
@endsection

@push('styles')
<style>
    .status-panel {
        border-radius: 20px;
        padding: 48px 40px;
        text-align: center;
        transition: background .4s, border-color .4s;
    }
    .status-icon {
        font-size: 5rem;
        line-height: 1;
        margin-bottom: 20px;
        display: block;
    }
    .status-text { font-size: 2.4rem; font-weight: 800; letter-spacing: .03em; }
    .status-sub  { font-size: 1.1rem; margin-top: 8px; }
    .ref-badge   { font-size: 1rem; font-family: monospace; letter-spacing: .08em; }

    /* State colours */
    .state-pending  { background: #fffde7; border: 3px solid #ffc107; color: #795548; }
    .state-cleared  { background: #e8f5e9; border: 3px solid #4caf50; color: #1b5e20; }
    .state-hold     { background: #fff3e0; border: 3px solid #ff9800; color: #e65100; }
    .state-rejected { background: #ffebee; border: 3px solid #f44336; color: #b71c1c; }

    .pulse { animation: pulse 1.5s infinite; }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50%       { opacity: .45; }
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex align-items-center gap-3">
    <a href="{{ route('guard-post.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0">Capture Status</h4>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">

        {{-- ── Status Panel ──────────────────────────────────────────────── --}}
        <div class="status-panel mb-4 state-{{ $capture->status }}" id="statusPanel">
            <span class="status-icon" id="statusIcon">
                @if($capture->status === 'pending')  <i class="bi bi-hourglass-split pulse"></i>
                @elseif($capture->status === 'cleared')  <i class="bi bi-check-circle-fill"></i>
                @elseif($capture->status === 'hold')     <i class="bi bi-exclamation-triangle-fill"></i>
                @else                                    <i class="bi bi-x-circle-fill"></i>
                @endif
            </span>
            <div class="status-text" id="statusText">{{ strtoupper($capture->status_label) }}</div>
            <div class="status-sub" id="statusSub">
                @if($capture->status === 'pending')
                    Waiting for clearance — please stand by
                @elseif($capture->status === 'cleared')
                    Vehicle may proceed
                @elseif($capture->status === 'hold')
                    Do not allow entry — contact Operations Desk
                @else
                    Entry not permitted — contact supervisor
                @endif
            </div>
            @if($capture->clearance_note)
            <div class="mt-3 px-3 py-2 rounded" style="background:rgba(0,0,0,.06);font-size:.9rem;" id="clearanceNote">
                <i class="bi bi-chat-left-text me-2"></i>{{ $capture->clearance_note }}
            </div>
            @else
            <div id="clearanceNote"></div>
            @endif
        </div>

        {{-- ── Capture Summary ───────────────────────────────────────────── --}}
        <div class="card content-card mb-3">
            <div class="card-header py-2">
                <i class="bi bi-info-circle me-2 text-primary"></i>Capture Summary
            </div>
            <div class="card-body">
                <dl class="row mb-0" style="font-size:.9rem;">
                    <dt class="col-sm-4">Reference</dt>
                    <dd class="col-sm-8 font-monospace fw-bold">{{ $capture->reference_no }}</dd>

                    <dt class="col-sm-4">Direction</dt>
                    <dd class="col-sm-8">
                        @if($capture->direction === 'gate_in')
                            <span class="badge bg-success"><i class="bi bi-box-arrow-in-right me-1"></i>Gate In</span>
                        @else
                            <span class="badge bg-primary"><i class="bi bi-box-arrow-right me-1"></i>Gate Out</span>
                        @endif
                    </dd>

                    @if($capture->container_number)
                    <dt class="col-sm-4">Container</dt>
                    <dd class="col-sm-8 font-monospace">{{ $capture->container_number }}
                        @if($capture->iso_code)<span class="badge bg-secondary ms-1">{{ $capture->iso_code }}</span>@endif
                    </dd>
                    @endif

                    @if($capture->vehicle_number)
                    <dt class="col-sm-4">Vehicle</dt>
                    <dd class="col-sm-8 font-monospace">{{ $capture->vehicle_number }}</dd>
                    @endif

                    @if($capture->driver_name)
                    <dt class="col-sm-4">Driver</dt>
                    <dd class="col-sm-8">{{ $capture->driver_name }}</dd>
                    @endif

                    <dt class="col-sm-4">Submitted</dt>
                    <dd class="col-sm-8 text-muted">{{ $capture->captured_at->format('d M Y, h:i A') }}</dd>

                    @if($capture->cleared_at)
                    <dt class="col-sm-4">Actioned</dt>
                    <dd class="col-sm-8 text-muted">
                        {{ $capture->cleared_at->format('h:i A') }}
                        @if($capture->clearedBy) by {{ $capture->clearedBy->full_name }}@endif
                    </dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Photos row --}}
        @php
            $photos = array_filter([
                'Container' => $capture->container_image_url,
                'Plate'     => $capture->plate_image_url,
                'NIC Front' => $capture->nic_front_url,
                'NIC Back'  => $capture->nic_back_url,
                'Licence'   => $capture->license_front_url,
            ]);
        @endphp
        @if(count($photos))
        <div class="card content-card mb-3">
            <div class="card-header py-2"><i class="bi bi-images me-2 text-primary"></i>Captured Photos</div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($photos as $label => $url)
                    <div class="col-4 col-md-3">
                        <a href="{{ $url }}" target="_blank">
                            <img src="{{ $url }}" class="img-thumbnail w-100"
                                 style="height:80px;object-fit:cover;" alt="{{ $label }}">
                        </a>
                        <div class="text-center text-muted mt-1" style="font-size:.72rem;">{{ $label }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <div class="d-flex gap-2 justify-content-between">
            <a href="{{ route('guard-post.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-house me-1"></i>Guard Post Home
            </a>
            <a href="{{ route('guard-post.create', ['direction' => $capture->direction]) }}"
               class="btn btn-outline-primary">
                <i class="bi bi-plus-circle me-1"></i>New Capture
            </a>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Only poll while status is pending
    if ('{{ $capture->status }}' !== 'pending') return;

    var pollUrl  = '{{ route('guard-post.status-json', $capture) }}';
    var interval = null;

    var stateMap = {
        pending:  { cls: 'state-pending',  icon: '<i class="bi bi-hourglass-split pulse"></i>', text: 'PENDING',  sub: 'Waiting for clearance — please stand by' },
        cleared:  { cls: 'state-cleared',  icon: '<i class="bi bi-check-circle-fill"></i>',     text: 'CLEARED',  sub: 'Vehicle may proceed' },
        hold:     { cls: 'state-hold',     icon: '<i class="bi bi-exclamation-triangle-fill"></i>', text: 'ON HOLD', sub: 'Do not allow entry — contact Operations Desk' },
        rejected: { cls: 'state-rejected', icon: '<i class="bi bi-x-circle-fill"></i>',         text: 'REJECTED', sub: 'Entry not permitted — contact supervisor' },
    };

    function applyState(data) {
        var panel = document.getElementById('statusPanel');
        var state = stateMap[data.status] || stateMap.pending;
        panel.className = 'status-panel mb-4 ' + state.cls;
        document.getElementById('statusIcon').innerHTML = state.icon;
        document.getElementById('statusText').textContent = state.text;
        document.getElementById('statusSub').textContent  = state.sub;
        var noteEl = document.getElementById('clearanceNote');
        if (data.clearance_note) {
            noteEl.innerHTML = '<div class="mt-3 px-3 py-2 rounded" style="background:rgba(0,0,0,.06);font-size:.9rem;"><i class="bi bi-chat-left-text me-2"></i>' + data.clearance_note + '</div>';
        }
        if (data.status !== 'pending' && interval) {
            clearInterval(interval);
        }
    }

    function poll() {
        fetch(pollUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(applyState)
            .catch(function () {});
    }

    interval = setInterval(poll, 10000); // poll every 10 seconds
})();
</script>
@endpush
