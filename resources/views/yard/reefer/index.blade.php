@extends('layouts.app')
@section('title', 'Reefer Plug Sessions')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-0 fw-semibold"><i class="bi bi-plug-fill text-primary me-2"></i>Reefer Plug Sessions</h4>
        <p class="text-muted small mb-0">Manage electricity plug-in / plug-out for laden reefer containers.</p>
    </div>
    <a href="{{ route('billing.reefer.create') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-file-earmark-plus me-1"></i>Create Invoice
    </a>
</div>

{{-- Stats row --}}
<div class="row g-3 mb-4">
    @php
        $statCards = [
            ['label'=>'Pending Plug-In',  'value'=>$stats['pending'],   'icon'=>'bi-hourglass',       'class'=>'text-warning'],
            ['label'=>'Currently Active', 'value'=>$stats['active'],    'icon'=>'bi-lightning-charge', 'class'=>'text-success'],
            ['label'=>'Ready to Bill',    'value'=>$stats['completed'], 'icon'=>'bi-check-circle',     'class'=>'text-info'],
            ['label'=>'Billed',           'value'=>$stats['billed'],    'icon'=>'bi-receipt',          'class'=>'text-secondary'],
        ];
    @endphp
    @foreach($statCards as $sc)
    <div class="col-6 col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body text-center py-3">
                <i class="bi {{ $sc['icon'] }} fs-4 {{ $sc['class'] }} d-block mb-1"></i>
                <div class="fs-3 fw-bold">{{ $sc['value'] }}</div>
                <div class="text-muted small">{{ $sc['label'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>


{{-- Filters --}}
<form method="GET" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Container No" value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="customer_id" class="form-select form-select-sm select2">
            <option value="">All Customers</option>
            @foreach($customers as $c)
                <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
            <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Active</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
            <option value="billed"    {{ request('status') === 'billed'    ? 'selected' : '' }}>Billed</option>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <a href="{{ route('yard.reefer.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Container</th>
                    <th>Customer</th>
                    <th>Plug-In</th>
                    <th>Plug-Out</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions as $session)
                <tr>
                    <td>
                        <span class="font-monospace fw-medium">{{ $session->container?->container_no }}</span>
                        <div class="text-muted small">{{ $session->container?->equipmentType?->dropdown_label ?? '—' }}</div>
                    </td>
                    <td>{{ $session->customer?->name }}</td>
                    <td>
                        @if($session->plug_in_at)
                            {{ $session->plug_in_at->format('d M Y H:i') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($session->plug_out_at)
                            {{ $session->plug_out_at->format('d M Y H:i') }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($session->plug_in_at)
                            @php
                                $end = $session->plug_out_at ?? now();
                                $hrs = round($session->plug_in_at->diffInMinutes($end) / 60, 1);
                                $days = $session->totalDays();
                            @endphp
                            <span class="small">{{ $hrs }}h / {{ $days }}d</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $session->status_badge_class }}">{{ ucfirst($session->status) }}</span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('yard.reefer.show', $session) }}" class="btn btn-sm btn-outline-secondary me-1" title="View">
                            <i class="bi bi-eye"></i>
                        </a>
                        @if($session->isPending())
                            <a href="{{ route('yard.reefer.plug-in', $session) }}" class="btn btn-sm btn-success" title="Record Plug-In">
                                <i class="bi bi-plug-fill"></i> Plug In
                            </a>
                        @elseif($session->isActive())
                            <a href="{{ route('yard.reefer.plug-out', $session) }}" class="btn btn-sm btn-danger" title="Record Plug-Out">
                                <i class="bi bi-plug"></i> Plug Out
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-plug fs-2 d-block mb-2 opacity-25"></i>
                        No reefer plug sessions found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($sessions->hasPages())
    <div class="card-footer d-flex justify-content-end">
        {{ $sessions->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
