@extends('layouts.app')

@section('title', 'Container Master')

@section('breadcrumb')
    <li class="breadcrumb-item active">Container Master</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-boxes me-2 text-primary"></i>Container Master</h4>
        <p class="text-muted mb-0 small">Master registry for all consignee, owned and leased containers</p>
    </div>
    <a href="{{ route('containers.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Container
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Summary counters --}}
<div class="row g-3 mb-3">
    @php
        $totalCount     = $containers->total();
        $inYardCount    = \App\Models\Container::where('status','in_yard')->count();
        $ownedCount     = \App\Models\Container::where('category','owned')->count();
        $leasedCount    = \App\Models\Container::where('category','leased')->count();
    @endphp
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-boxes"></i></div>
                <div><div class="text-muted small">Total Registered</div><div class="fs-5 fw-bold">{{ number_format($totalCount) }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-geo-alt"></i></div>
                <div><div class="text-muted small">In Yard</div><div class="fs-5 fw-bold">{{ number_format($inYardCount) }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-info-subtle text-info"><i class="bi bi-box-seam"></i></div>
                <div><div class="text-muted small">Owned</div><div class="fs-5 fw-bold">{{ number_format($ownedCount) }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning"><i class="bi bi-file-earmark-text"></i></div>
                <div><div class="text-muted small">Leased</div><div class="fs-5 fw-bold">{{ number_format($leasedCount) }}</div></div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('containers.index') }}">
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Container no., owner, manufacturer…"
                           value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <option value="consignee" {{ request('category')=='consignee'?'selected':'' }}>Consignee</option>
                    <option value="owned"     {{ request('category')=='owned'    ?'selected':'' }}>Owned</option>
                    <option value="leased"    {{ request('category')=='leased'   ?'selected':'' }}>Leased</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="size" class="form-select form-select-sm">
                    <option value="">All Sizes</option>
                    <option value="20" {{ request('size')=='20'?'selected':'' }}>20ft</option>
                    <option value="40" {{ request('size')=='40'?'selected':'' }}>40ft</option>
                    <option value="45" {{ request('size')=='45'?'selected':'' }}>45ft</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="in_yard"   {{ request('status')=='in_yard'  ?'selected':'' }}>In Yard</option>
                    <option value="in_repair" {{ request('status')=='in_repair'?'selected':'' }}>In Repair</option>
                    <option value="reserved"  {{ request('status')=='reserved' ?'selected':'' }}>Reserved</option>
                    <option value="released"  {{ request('status')=='released' ?'selected':'' }}>Released</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                <a href="{{ route('containers.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</div>
</form>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Container No.</th>
                        <th>Category</th>
                        <th>Size / Type</th>
                        <th>Year / Mfr</th>
                        <th>Owner</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Location</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($containers as $c)
                <tr>
                    <td>
                        <a href="{{ route('containers.show', $c) }}" class="fw-semibold text-decoration-none">
                            {{ $c->container_no }}
                        </a>
                    </td>
                    <td>
                        @php
                            $catClass = ['consignee'=>'bg-secondary','owned'=>'bg-info','leased'=>'bg-warning text-dark'];
                        @endphp
                        <span class="badge {{ $catClass[$c->category] ?? 'bg-secondary' }}">
                            {{ ucfirst($c->category) }}
                        </span>
                    </td>
                    <td>{{ $c->size ? $c->size.'ft '.$c->type_code : '—' }}</td>
                    <td class="text-muted small">{{ $c->manufacture_year ?? '—' }}{{ $c->manufacturer ? ' / '.$c->manufacturer : '' }}</td>
                    <td class="small">{{ $c->owner_name ?? $c->owner_code ?? '—' }}</td>
                    <td class="small">{{ $c->customer?->name ?? '—' }}</td>
                    <td>
                        @php
                            $statusClass = [
                                'in_yard'   => 'bg-success',
                                'in_repair' => 'bg-warning text-dark',
                                'reserved'  => 'bg-info',
                                'released'  => 'bg-secondary',
                            ];
                        @endphp
                        <span class="badge {{ $statusClass[$c->status] ?? 'bg-secondary' }}">
                            {{ str_replace('_', ' ', ucfirst($c->status ?? 'unknown')) }}
                        </span>
                    </td>
                    <td class="small text-muted">
                        @if($c->location_zone)
                            {{ $c->location_zone }}-{{ $c->location_row }}{{ $c->location_bay }}-T{{ $c->location_tier }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('containers.show', $c) }}" class="btn btn-outline-primary btn-xs py-0 px-1">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('containers.edit', $c) }}" class="btn btn-outline-secondary btn-xs py-0 px-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No containers found.</td>
                </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($containers->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center py-2 small text-muted">
        <span>Showing {{ $containers->firstItem() }}–{{ $containers->lastItem() }} of {{ $containers->total() }}</span>
        {{ $containers->links() }}
    </div>
    @endif
</div>

@endsection
