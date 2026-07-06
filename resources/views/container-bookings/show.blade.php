@extends('layouts.app')

@section('title', 'Booking ' . $containerBooking->booking_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Containers</li>
    <li class="breadcrumb-item"><a href="{{ route('container-bookings.index') }}">Bookings</a></li>
    <li class="breadcrumb-item active">{{ $containerBooking->booking_no }}</li>
@endsection

@section('content')
@php
    $b = $containerBooking;
    $statusBadge = ['open'=>'bg-primary','partial'=>'bg-warning text-dark','fulfilled'=>'bg-success','cancelled'=>'bg-secondary','expired'=>'bg-dark'];
    $totalQ = $b->totalQuantity(); $totalR = $b->totalReleased(); $totalA = $b->totalAllocated();
    $pct = $totalQ > 0 ? round($totalR / $totalQ * 100) : 0;
@endphp

<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-journal-bookmark me-2 text-primary"></i>{{ $b->booking_no }}
            <span class="badge {{ $statusBadge[$b->status] ?? 'bg-secondary' }} align-middle">{{ ucfirst($b->status) }}</span>
        </h4>
        <p class="text-muted mb-0 small">
            {{ $b->customer->name ?? '—' }}
            @if($b->valid_from || $b->valid_to) · valid {{ optional($b->valid_from)->format('d M Y') }} – {{ optional($b->valid_to)->format('d M Y') }}@endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @if($b->isOpen())
        @can('container-bookings.cancel')
        <form method="POST" action="{{ route('container-bookings.cancel', $b) }}" onsubmit="return confirm('Cancel this booking and return all reserved containers to available?')">
            @csrf<button class="btn btn-sm btn-outline-warning"><i class="bi bi-x-circle me-1"></i>Cancel Booking</button>
        </form>
        @endcan
        @endif
        <a href="{{ route('container-bookings.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

<div class="card content-card mb-3">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between small mb-1">
            <span class="text-muted">Overall fulfilment</span>
            <span><strong>{{ $totalR }}</strong> released · {{ $totalA }} reserved · {{ $totalQ }} booked</span>
        </div>
        <div class="progress" style="height:10px;">
            <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
            <div class="progress-bar bg-warning" style="width: {{ $totalQ > 0 ? round($totalA / $totalQ * 100) : 0 }}%"></div>
        </div>
    </div>
</div>

@foreach($b->lines as $line)
@php
    $matching = $available->filter(fn ($c) => $c->size === $line->size && $c->type_code === $line->type_code);
    $reserved = $line->containers->where('status', 'reserved');
@endphp
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong class="small">{{ $line->label }}</strong>
        <span class="small text-muted">
            booked {{ $line->quantity }} · reserved {{ $line->allocated_qty }} · released {{ $line->released_qty }} ·
            <span class="{{ $line->unallocated > 0 ? 'text-warning fw-semibold' : 'text-success' }}">{{ $line->unallocated }} to allocate</span>
        </span>
    </div>
    <div class="card-body py-2">
        @if($b->isOpen() && $line->unallocated > 0)
        @can('container-bookings.allocate')
        <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
            <form method="POST" action="{{ route('container-bookings.auto-allocate', [$b, $line]) }}" class="d-flex gap-1 align-items-center">
                @csrf
                <input type="number" name="count" class="form-control form-control-sm" style="width:70px" min="1" max="{{ $line->unallocated }}" value="{{ $line->unallocated }}">
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-magic me-1"></i>Auto-allocate (oldest)</button>
            </form>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#pick{{ $line->id }}">
                <i class="bi bi-hand-index me-1"></i>Pick manually ({{ $matching->count() }} match)
            </button>
        </div>
        <div class="collapse" id="pick{{ $line->id }}">
            @if($matching->isEmpty())
                <p class="small text-muted mb-0">No available containers match {{ $line->size }} {{ $line->type_code }}.</p>
            @else
            <form method="POST" action="{{ route('container-bookings.allocate', $b) }}">
                @csrf
                <input type="hidden" name="line_id" value="{{ $line->id }}">
                <div class="row g-1 mb-2">
                    @foreach($matching as $c)
                    <div class="col-md-3 col-6">
                        <label class="d-flex align-items-center gap-1 small border rounded px-2 py-1">
                            <input type="checkbox" name="container_ids[]" value="{{ $c->id }}" class="form-check-input mt-0">
                            <span class="font-monospace">{{ $c->container_no }}</span>
                            @if($line->grade_id && $c->grade_id !== $line->grade_id)
                                <i class="bi bi-exclamation-triangle text-warning" title="Grade differs from the booking line"></i>
                            @endif
                        </label>
                    </div>
                    @endforeach
                </div>
                <button class="btn btn-sm btn-success"><i class="bi bi-link me-1"></i>Reserve selected</button>
                <span class="small text-muted ms-2">Grade mismatches are allowed (⚠ marked).</span>
            </form>
            @endif
        </div>
        @endcan
        @endif

        @if($reserved->isNotEmpty())
        <div class="mt-2">
            <div class="small text-muted mb-1">Reserved containers</div>
            <div class="d-flex flex-wrap gap-1">
                @foreach($reserved as $c)
                <span class="badge bg-info-subtle text-info border d-inline-flex align-items-center gap-1">
                    <span class="font-monospace">{{ $c->container_no }}</span>
                    @if($b->isOpen())
                    @can('container-bookings.allocate')
                    <form method="POST" action="{{ route('container-bookings.deallocate', [$b, $c]) }}" class="d-inline" onsubmit="return confirm('Release {{ $c->container_no }} back to available?')">
                        @csrf<button class="btn btn-close btn-close-sm p-0" style="font-size:.55rem" title="Deallocate"></button>
                    </form>
                    @endcan
                    @endif
                </span>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endforeach

@endsection
