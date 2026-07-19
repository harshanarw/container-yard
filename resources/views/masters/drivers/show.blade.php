@extends('layouts.app')

@section('title', 'Driver — ' . ($driver->name ?: $driver->nic_number))

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item"><a href="{{ route('masters.drivers.index') }}">Drivers</a></li>
    <li class="breadcrumb-item active">{{ $driver->name ?: $driver->nic_number }}</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>{{ $driver->name ?: '(no name)' }}</h4>
        <p class="text-muted mb-0 small">
            <span class="font-monospace">{{ $driver->nic_number }}</span>
            · {{ $driver->movement_count }} movement(s)
            · last seen {{ $driver->last_seen_at?->format('d M Y') ?? '—' }}
        </p>
    </div>
    <a href="{{ route('masters.drivers.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to list
    </a>
</div>

@if(session('success'))
<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2 small">
    <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-3">
    {{-- Edit details --}}
    <div class="col-lg-5">
        <div class="card content-card">
            <div class="card-header py-2"><i class="bi bi-pencil-square me-2 text-primary"></i>Details</div>
            <div class="card-body">
                <form method="POST" action="{{ route('masters.drivers.update', $driver) }}">
                    @csrf
                    @method('PATCH')
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $driver->name) }}" maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">NIC / Passport <span class="text-danger">*</span></label>
                        <input type="text" name="nic_number" class="form-control font-monospace" value="{{ old('nic_number', $driver->nic_number) }}" maxlength="30" required>
                        <div class="form-text">Changing this re-keys the master record (must stay unique).</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $driver->phone) }}" maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">License number</label>
                        <input type="text" name="license_number" class="form-control" value="{{ old('license_number', $driver->license_number) }}" maxlength="50">
                    </div>
                    <div class="d-flex justify-content-between">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save</button>
                        <button type="submit" form="deleteDriverForm" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Remove this driver from the master? Movement history is not deleted.');">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </div>
                </form>
                <form method="POST" action="{{ route('masters.drivers.destroy', $driver) }}" id="deleteDriverForm" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
        </div>

        {{-- Possible duplicates / merge --}}
        @if($duplicates->isNotEmpty())
        <div class="card content-card mt-3">
            <div class="card-header py-2"><i class="bi bi-people me-2 text-warning"></i>Possible duplicates</div>
            <div class="card-body">
                <p class="text-muted small mb-2">
                    These records share this driver's name or phone. Merging re-points their movement history
                    to <span class="font-monospace">{{ $driver->nic_number }}</span> and removes the duplicate.
                </p>
                <ul class="list-group list-group-flush">
                    @foreach($duplicates as $dup)
                    <li class="list-group-item d-flex align-items-center justify-content-between px-0">
                        <div>
                            <div class="fw-semibold small">{{ $dup->name ?: '(no name)' }}</div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <span class="font-monospace">{{ $dup->nic_number }}</span>
                                @if($dup->phone) · {{ $dup->phone }}@endif
                                · {{ $dup->movement_count }} mv
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="{{ route('masters.drivers.show', $dup) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            <form method="POST" action="{{ route('masters.drivers.merge') }}"
                                  onsubmit="return confirm('Merge {{ $dup->nic_number }} into {{ $driver->nic_number }}? This cannot be undone.');">
                                @csrf
                                <input type="hidden" name="survivor_id" value="{{ $driver->id }}">
                                <input type="hidden" name="duplicate_id" value="{{ $dup->id }}">
                                <button class="btn btn-sm btn-warning"><i class="bi bi-union me-1"></i>Merge in</button>
                            </form>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
    </div>

    {{-- Movement history --}}
    <div class="col-lg-7">
        <div class="card content-card">
            <div class="card-header py-2"><i class="bi bi-clock-history me-2 text-primary"></i>Movement history</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>When</th>
                                <th>Event</th>
                                <th>Reference</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($timeline as $t)
                            <tr>
                                <td class="small text-muted">{{ optional($t['when'])->format('d M Y H:i') ?? '—' }}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary border">{{ $t['label'] }}</span></td>
                                <td class="font-monospace small">
                                    @if($t['url'])<a href="{{ $t['url'] }}">{{ $t['ref'] }}</a>@else{{ $t['ref'] }}@endif
                                </td>
                                <td class="small">{{ $t['detail'] ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No movement history for this driver.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
