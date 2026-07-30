@extends('layouts.app')

@section('title', 'Working Hours')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('overtime.setup.index') }}">Overtime</a></li>
    <li class="breadcrumb-item active">Working Hours</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h4><i class="bi bi-clock me-2 text-primary"></i>Working Hours</h4>
        <p class="text-muted mb-0 small">
            Normal working windows per weekday. Any gate movement outside these hours is overtime,
            so this master decides when the OT tariff applies.
        </p>
    </div>
    @can('ot.settings.edit')
    <a href="{{ route('overtime.working-hours.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Working-Hour Set
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small"><i class="bi bi-exclamation-circle me-1"></i>{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@if($resolved)
<div class="alert alert-info py-2 small d-flex align-items-center gap-2">
    <i class="bi bi-info-circle"></i>
    <div>The overtime engine is using <strong>{{ $resolved->name }}</strong>@if(! $resolved->is_default)
        (fallback — no set is flagged default)@endif.</div>
</div>
@else
<div class="alert alert-danger py-2 small d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-octagon"></i>
    <div>No active working-hour set — every movement currently counts as overtime.</div>
</div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Set</th>
                        <th>Effective</th>
                        @foreach(\App\Models\WeeklyWorkingHour::DAYS as $label)
                            <th class="text-center small">{{ substr($label, 0, 3) }}</th>
                        @endforeach
                        <th class="text-center">Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sets as $set)
                    @php $days = $set->daysByName(); @endphp
                    <tr class="{{ $set->status === 'active' ? '' : 'opacity-50' }}">
                        <td class="ps-3">
                            <span class="fw-semibold">{{ $set->name }}</span>
                            @if($set->is_default)<span class="badge bg-primary ms-1">Default</span>@endif
                            @if($resolved && $resolved->is($set))<span class="badge bg-success-subtle text-success border ms-1">In use</span>@endif
                        </td>
                        <td class="small text-muted">
                            {{ $set->effective_from?->format('d M Y') ?? '—' }}
                            @if($set->effective_to) → {{ $set->effective_to->format('d M Y') }} @endif
                        </td>
                        @foreach(array_keys(\App\Models\WeeklyWorkingHour::DAYS) as $key)
                            @php $day = $days->get($key); @endphp
                            <td class="text-center small">
                                @if($day && $day->is_regular_working_day && $day->normal_start_time)
                                    <span class="font-monospace text-nowrap" style="font-size:.72rem">
                                        {{ substr((string) $day->normal_start_time, 0, 5) }}<br>{{ substr((string) $day->normal_end_time, 0, 5) }}
                                    </span>
                                @else
                                    <span class="text-muted" title="Closed">—</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="text-center">
                            <span class="badge bg-{{ $set->status === 'active' ? 'success' : ($set->status === 'draft' ? 'secondary' : 'dark') }}">
                                {{ $set->statusLabel() }}
                            </span>
                        </td>
                        <td class="text-end pe-3 text-nowrap">
                            @can('ot.settings.edit')
                            <a href="{{ route('overtime.working-hours.edit', $set) }}" class="btn btn-outline-secondary btn-xs py-0 px-1" title="Edit"><i class="bi bi-pencil"></i></a>
                            @if(! $set->is_default && $set->status === 'active')
                            <form method="POST" action="{{ route('overtime.working-hours.default', $set) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-primary btn-xs py-0 px-1" title="Make default"><i class="bi bi-star"></i></button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('overtime.working-hours.destroy', $set) }}" class="d-inline"
                                  data-confirm="Delete the working-hour set &quot;{{ $set->name }}&quot;? This cannot be undone."
                                  data-confirm-title="Delete Working-Hour Set"
                                  data-confirm-class="btn-danger" data-confirm-label="Delete">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-xs py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>No working-hour sets defined yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
