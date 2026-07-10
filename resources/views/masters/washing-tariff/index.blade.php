@extends('layouts.app')

@section('title', 'Washing Tariff')

@section('breadcrumb')
    <li class="breadcrumb-item">Masters</li>
    <li class="breadcrumb-item">Tariffs</li>
    <li class="breadcrumb-item active">Washing / Cleaning</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-droplet me-2 text-primary"></i>Washing / Cleaning Tariff</h4>
        <p class="text-muted mb-0 small">Flat per-container cleaning rates, split into internal and external scope. Rates are held in USD; estimates convert on the estimate date.</p>
    </div>
    @can('masters.washing-tariff.create')
    <a href="{{ route('masters.washing-tariff.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Add Washing Rate
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filters --}}
<form method="GET" action="{{ route('masters.washing-tariff.index') }}" class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-6 col-md-3">
                <select name="scope" class="form-select form-select-sm">
                    <option value="">All scopes</option>
                    @foreach(\App\Models\WashingTariff::SCOPES as $k => $label)
                        <option value="{{ $k }}" {{ request('scope') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">All rates</option>
                    <option value="default" {{ request('customer_id') === 'default' ? 'selected' : '' }}>Default (no customer)</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->id }}" {{ (string) request('customer_id') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('masters.washing-tariff.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </div>
</form>

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Applies To</th>
                        <th>Scope</th>
                        <th>Wash Type</th>
                        <th>Size</th>
                        <th class="text-end">Rate</th>
                        <th class="text-end">Min</th>
                        <th>Charge / Tax</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tariffs as $t)
                <tr class="{{ $t->is_active ? '' : 'opacity-50' }}">
                    <td class="ps-3">
                        @if($t->customer)
                            <span class="fw-semibold">{{ $t->customer->name }}</span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border">Default</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $t->wash_scope === 'internal' ? 'bg-info' : 'bg-primary' }}">{{ $t->scope_label }}</span>
                    </td>
                    <td class="small">{{ $t->type_label }}</td>
                    <td class="small">{{ $t->size_label }}</td>
                    <td class="text-end fw-semibold">{{ $t->currency }} {{ number_format($t->rate, 2) }}</td>
                    <td class="text-end small text-muted">{{ $t->min_charge !== null ? number_format($t->min_charge, 2) : '—' }}</td>
                    <td class="small">
                        {{ $t->chargeCode?->code ?? '—' }}
                        @if($t->taxCode)<span class="text-muted">· {{ $t->taxCode->code }}</span>@endif
                    </td>
                    <td>
                        @if($t->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end pe-3">
                        @can('masters.washing-tariff.edit')
                        <a href="{{ route('masters.washing-tariff.edit', $t) }}" class="btn btn-outline-secondary btn-xs py-0 px-1"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('masters.washing-tariff.toggle', $t) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-outline-{{ $t->is_active ? 'warning' : 'success' }} btn-xs py-0 px-1" title="{{ $t->is_active ? 'Deactivate' : 'Activate' }}">
                                <i class="bi bi-{{ $t->is_active ? 'pause' : 'play' }}"></i>
                            </button>
                        </form>
                        @endcan
                        @can('masters.washing-tariff.delete')
                        <form method="POST" action="{{ route('masters.washing-tariff.destroy', $t) }}" class="d-inline"
                              data-confirm="Delete this washing rate? This cannot be undone."
                              data-confirm-title="Delete Washing Rate"
                              data-confirm-class="btn-danger"
                              data-confirm-label="Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-xs py-0 px-1"><i class="bi bi-trash"></i></button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No washing rates defined yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
