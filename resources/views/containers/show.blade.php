@extends('layouts.app')

@section('title', $container->container_no)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('containers.index') }}" class="text-decoration-none">Container Master</a></li>
    <li class="breadcrumb-item active">{{ $container->container_no }}</li>
@endsection

@section('content')

@php
    $catBadge = ['consignee'=>'bg-secondary','owned'=>'bg-info','leased'=>'bg-warning text-dark'];
    $statusBadge = ['in_yard'=>'bg-success','in_repair'=>'bg-warning text-dark','reserved'=>'bg-info','released'=>'bg-secondary'];
    $today = \Carbon\Carbon::today();
    $cscExpired = $container->csc_expiry_date && $container->csc_expiry_date->lt($today);
    $cscSoon    = $container->csc_expiry_date && !$cscExpired && $container->csc_expiry_date->lt($today->addDays(90));
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4>
            <i class="bi bi-boxes me-2 text-primary"></i>
            {{ $container->container_no }}
            <span class="badge {{ $catBadge[$container->category] ?? 'bg-secondary' }} ms-2" style="font-size:.7rem; vertical-align:middle;">
                {{ ucfirst($container->category) }}
            </span>
            <span class="badge {{ $statusBadge[$container->status] ?? 'bg-secondary' }} ms-1" style="font-size:.7rem; vertical-align:middle;">
                {{ str_replace('_',' ',ucfirst($container->status ?? '')) }}
            </span>
        </h4>
        <p class="text-muted mb-0 small">Container master profile</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('containers.edit', $container) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <form method="POST" action="{{ route('containers.destroy', $container) }}"
              onsubmit="return confirm('Delete this container master record? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row g-4">

    {{-- Left: Master details --}}
    <div class="col-lg-8">

        <div class="card content-card mb-4">
            <div class="card-header py-2">
                <i class="bi bi-upc me-2 text-primary"></i>Container Profile
            </div>
            <div class="card-body">
                <div class="row g-0">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><th class="text-muted fw-normal" style="width:40%">Container No.</th><td class="fw-semibold">{{ $container->container_no }}</td></tr>
                            <tr><th class="text-muted fw-normal">Category</th>
                                <td><span class="badge {{ $catBadge[$container->category] ?? 'bg-secondary' }}">{{ ucfirst($container->category) }}</span></td></tr>
                            <tr><th class="text-muted fw-normal">Equipment Type</th><td>{{ $container->equipmentType?->name ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Size / Type</th><td>{{ $container->size ? $container->size.'ft '.$container->type_code : '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Mfr. Year</th><td>{{ $container->manufacture_year ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Manufacturer</th><td>{{ $container->manufacturer ?? '—' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0 small">
                            <tr><th class="text-muted fw-normal" style="width:40%">Owner Code</th><td>{{ $container->owner_code ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Owner Name</th><td>{{ $container->owner_name ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Customer</th>
                                <td>
                                    @if($container->customer)
                                        <a href="{{ route('customers.show', $container->customer) }}" class="text-decoration-none">
                                            {{ $container->customer->name }}
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            <tr><th class="text-muted fw-normal">Gross Weight</th><td>{{ $container->gross_weight_kg ? number_format($container->gross_weight_kg).' kg' : '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Tare Weight</th><td>{{ $container->tare_weight_kg ? number_format($container->tare_weight_kg).' kg' : '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Max Payload</th><td>{{ $container->max_payload_kg ? number_format($container->max_payload_kg).' kg' : '—' }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- CSC Plate --}}
        <div class="card content-card mb-4">
            <div class="card-header py-2">
                <i class="bi bi-shield-check me-2 text-primary"></i>CSC Safety Plate
                @if($cscExpired)
                    <span class="badge bg-danger ms-2">Expired</span>
                @elseif($cscSoon)
                    <span class="badge bg-warning text-dark ms-2">Expiring Soon</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-0 small">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><th class="text-muted fw-normal" style="width:45%">Plate No.</th><td>{{ $container->csc_plate_no ?? '—' }}</td></tr>
                            <tr><th class="text-muted fw-normal">Expiry Date</th>
                                <td class="{{ $cscExpired ? 'text-danger fw-semibold' : ($cscSoon ? 'text-warning fw-semibold' : '') }}">
                                    {{ $container->csc_expiry_date?->format('d M Y') ?? '—' }}
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if($container->notes)
        <div class="card content-card mb-4">
            <div class="card-header py-2">
                <i class="bi bi-chat-text me-2 text-primary"></i>Notes
            </div>
            <div class="card-body small">
                {{ $container->notes }}
            </div>
        </div>
        @endif

        {{-- Gate Movement History --}}
        <div class="card content-card">
            <div class="card-header py-2 d-flex align-items-center justify-content-between">
                <span><i class="bi bi-clock-history me-2 text-primary"></i>Gate Movement History</span>
                <span class="badge bg-secondary">{{ $container->gateMovements->count() }} recent</span>
            </div>
            <div class="card-body p-0">
                @if($container->gateMovements->isEmpty())
                    <p class="text-muted small text-center py-3 mb-0">No gate movements recorded yet.</p>
                @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Type</th>
                                <th>Date / Time</th>
                                <th>Customer</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Vehicle</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($container->gateMovements as $mv)
                        <tr>
                            <td>
                                @if($mv->movement_type === 'in')
                                    <span class="badge bg-success">IN</span>
                                @else
                                    <span class="badge bg-secondary">OUT</span>
                                @endif
                            </td>
                            <td>
                                {{ $mv->gate_in_time?->format('d M Y H:i') ?? $mv->gate_out_time?->format('d M Y H:i') ?? '—' }}
                            </td>
                            <td>{{ $mv->customer?->name ?? '—' }}</td>
                            <td class="text-muted">
                                @if($mv->location_zone)
                                    {{ $mv->location_zone }}-{{ $mv->location_row }}{{ $mv->location_bay }}-T{{ $mv->location_tier }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ ucfirst(str_replace('_',' ',$mv->condition ?? '')) ?: '—' }}</td>
                            <td>{{ $mv->vehicle_plate ?? '—' }}</td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Right: Yard status --}}
    <div class="col-lg-4">

        <div class="card content-card mb-4">
            <div class="card-header py-2">
                <i class="bi bi-geo-alt me-2 text-primary"></i>Yard Status
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6 text-muted">Current Status</dt>
                    <dd class="col-6">
                        <span class="badge {{ $statusBadge[$container->status] ?? 'bg-secondary' }}">
                            {{ str_replace('_',' ',ucfirst($container->status ?? '')) }}
                        </span>
                    </dd>
                    <dt class="col-6 text-muted">Location</dt>
                    <dd class="col-6">
                        @if($container->location_zone)
                            <span class="fw-semibold">
                                {{ $container->location_zone }}-{{ $container->location_row }}{{ $container->location_bay }}-T{{ $container->location_tier }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </dd>
                    <dt class="col-6 text-muted">Gate In</dt>
                    <dd class="col-6">{{ $container->gate_in_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-6 text-muted">Gate Out</dt>
                    <dd class="col-6">{{ $container->gate_out_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-6 text-muted">Days in Yard</dt>
                    <dd class="col-6">
                        @if($container->status === 'in_yard' && $container->gate_in_date)
                            {{ $container->gate_in_date->diffInDays(\Carbon\Carbon::today()) }}
                        @else
                            —
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        @if($container->yardLocation)
        <div class="card content-card mb-4">
            <div class="card-header py-2">
                <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Slot Assignment
            </div>
            <div class="card-body small">
                @php $loc = $container->yardLocation; @endphp
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Zone</dt><dd class="col-7">{{ $loc->zone }}</dd>
                    <dt class="col-5 text-muted">Row</dt><dd class="col-7">{{ $loc->row }}</dd>
                    <dt class="col-5 text-muted">Bay</dt><dd class="col-7">{{ $loc->bay }}</dd>
                    <dt class="col-5 text-muted">Tier</dt><dd class="col-7">{{ $loc->tier }}</dd>
                </dl>
            </div>
        </div>
        @endif

    </div>

</div>

@endsection
