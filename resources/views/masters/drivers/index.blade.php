@extends('layouts.app')

@section('title', 'Drivers')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item active">Drivers</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>Drivers</h4>
        <p class="text-muted mb-0 small">
            Master list of drivers, built automatically from gate movements and Guard Post captures.
            Search by name, NIC/passport or phone.
        </p>
    </div>
    <form method="GET" action="{{ route('masters.drivers.index') }}" class="d-flex gap-2" role="search">
        <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm"
               style="min-width:240px;" placeholder="Search name / NIC / phone…" autofocus>
        <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
        @if($q !== '')
        <a href="{{ route('masters.drivers.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        @endif
    </form>
</div>

@if(session('success'))
<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>NIC / Passport</th>
                        <th>Phone</th>
                        <th class="text-center">Movements</th>
                        <th>Last seen</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drivers as $driver)
                    <tr>
                        <td class="fw-semibold">{{ $driver->name ?: '—' }}</td>
                        <td class="font-monospace">{{ $driver->nic_number }}</td>
                        <td>{{ $driver->phone ?: '—' }}</td>
                        <td class="text-center">{{ $driver->movement_count }}</td>
                        <td class="small text-muted">{{ $driver->last_seen_at?->format('d M Y') ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('masters.drivers.show', $driver) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox me-1"></i>
                            {{ $q !== '' ? 'No drivers match your search.' : 'No drivers yet — they appear here as gate movements are recorded.' }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($drivers->hasPages())
<div class="mt-3">{{ $drivers->links() }}</div>
@endif

@endsection
