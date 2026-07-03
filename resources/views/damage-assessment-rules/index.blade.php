@extends('layouts.app')

@section('title', 'Damage Assessment Rules')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Inspection</li>
    <li class="breadcrumb-item active">Assessment Rules</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-journal-check me-2 text-primary"></i>Damage Assessment Rules</h4>
        <p class="text-muted small mb-0">
            Pre-define common Location / Component / Damage / Repair combinations.
            Use <strong>Pull From Rules</strong> in the Survey form to instantly populate assessment lines.
        </p>
    </div>
    @can('masters.damage-rules.create')
    <a href="{{ route('masters.damage-assessment-rules.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Rule
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

{{-- Filter bar --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search rule name…" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="location_code_id" class="form-select form-select-sm select2">
                    <option value="">All Locations</option>
                    @foreach($locations as $c)
                    <option value="{{ $c->id }}" {{ request('location_code_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->code }} — {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="component_code_id" class="form-select form-select-sm select2">
                    <option value="">All Components</option>
                    @foreach($components as $c)
                    <option value="{{ $c->id }}" {{ request('component_code_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->code }} — {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="damage_code_id" class="form-select form-select-sm select2">
                    <option value="">All Damage Types</option>
                    @foreach($damages as $c)
                    <option value="{{ $c->id }}" {{ request('damage_code_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->code }} — {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <select name="active" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="{{ route('masters.damage-assessment-rules.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex align-items-center justify-content-between py-2">
        <span class="small fw-semibold">{{ $rules->total() }} rule{{ $rules->total() !== 1 ? 's' : '' }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-sm">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width:220px">Rule Name</th>
                    <th style="width:120px">Location</th>
                    <th style="width:140px">Component</th>
                    <th style="width:130px">Damage</th>
                    <th style="width:150px">Repair</th>
                    <th style="width:90px">Severity</th>
                    <th>Description</th>
                    <th style="width:60px" class="text-center">Status</th>
                    <th style="width:110px" class="text-end pe-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rules as $rule)
                <tr class="{{ $rule->is_active ? '' : 'table-secondary text-muted' }}">
                    <td class="ps-3 fw-semibold small">{{ $rule->name }}</td>
                    <td class="small">
                        @if($rule->locationCode)
                            <span class="badge bg-secondary-subtle text-secondary border font-monospace">{{ $rule->locationCode->code }}</span>
                            <span class="text-muted">{{ $rule->locationCode->name }}</span>
                        @else
                            <span class="text-muted fst-italic">Any</span>
                        @endif
                    </td>
                    <td class="small">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">{{ $rule->componentCode->code }}</span>
                        <span class="text-muted">{{ $rule->componentCode->name }}</span>
                    </td>
                    <td class="small">
                        <span class="badge bg-warning-subtle text-warning-emphasis border font-monospace">{{ $rule->damageCode->code }}</span>
                        <span class="text-muted">{{ $rule->damageCode->name }}</span>
                    </td>
                    <td class="small">
                        <span class="badge bg-info-subtle text-info-emphasis border font-monospace">{{ $rule->repairCode->code }}</span>
                        <span class="text-muted">{{ $rule->repairCode->name }}</span>
                    </td>
                    <td class="small">
                        @if($rule->default_severity)
                            @php
                                $sc = match($rule->default_severity) {
                                    'severe'   => 'bg-danger',
                                    'moderate' => 'bg-warning text-dark',
                                    default    => 'bg-light text-dark border',
                                };
                            @endphp
                            <span class="badge {{ $sc }}">{{ ucfirst($rule->default_severity) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ Str::limit($rule->description, 60) ?? '—' }}</td>
                    <td class="text-center">
                        @can('masters.damage-rules.edit')
                        <form method="POST" action="{{ route('masters.damage-assessment-rules.toggle', $rule) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-success' : 'btn-outline-secondary' }}" title="{{ $rule->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}">
                                <i class="bi {{ $rule->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                            </button>
                        </form>
                        @endcan
                    </td>
                    <td class="text-end pe-3">
                        <div class="d-flex justify-content-end gap-1">
                            @can('masters.damage-rules.edit')
                            <a href="{{ route('masters.damage-assessment-rules.edit', $rule) }}"
                               class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                            @can('masters.damage-rules.delete')
                            <form method="POST" action="{{ route('masters.damage-assessment-rules.destroy', $rule) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        data-confirm="Delete rule &ldquo;{{ $rule->name }}&rdquo;? This cannot be undone."
                                        data-confirm-title="Delete Rule"
                                        data-confirm-class="btn-danger"
                                        data-confirm-label="Delete"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x d-block fs-2 mb-2"></i>
                        No assessment rules found.
                        <a href="{{ route('masters.damage-assessment-rules.create') }}">Create the first one</a> or run the seeder to load defaults.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($rules->hasPages())
    <div class="card-footer bg-light">
        {{ $rules->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

@endsection
