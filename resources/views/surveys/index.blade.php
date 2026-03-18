@extends('layouts.app')

@section('title', 'Container Surveys')

@section('breadcrumb')
    <li class="breadcrumb-item active">Container Surveys</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-card-checklist me-2 text-primary"></i>Container Surveys</h4>
        <p class="text-muted mb-0 small">Process damage surveys and pre-trip inspections</p>
    </div>
    <a href="{{ route('surveys.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>New Survey
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<!-- Status Tabs -->
@php
    $statuses = ['all' => 'All', 'open' => 'Open', 'in_progress' => 'In Progress',
                 'estimate_sent' => 'Estimate Sent', 'approved' => 'Approved', 'closed' => 'Closed'];
    $tabColors = ['all'=>'secondary','open'=>'warning','in_progress'=>'primary',
                  'estimate_sent'=>'info','approved'=>'success','closed'=>'dark'];
    $currentStatus = request('status', 'all');
@endphp
<ul class="nav nav-tabs mb-0">
    @foreach($statuses as $key => $label)
    <li class="nav-item">
        <a class="nav-link {{ $currentStatus === $key ? 'active' : '' }}"
           href="{{ route('surveys.index', array_merge(request()->except('status','page'), $key === 'all' ? [] : ['status' => $key])) }}">
            {{ $label }}
        </a>
    </li>
    @endforeach
</ul>

<!-- Filters -->
<form method="GET" action="{{ route('surveys.index') }}">
    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
    <div class="card content-card filter-panel mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Survey no., container, customer…"
                               value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">All Customers</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="inquiry_type" class="form-select form-select-sm">
                        <option value="">All Types</option>
                        <option value="damage_survey"           {{ request('inquiry_type') === 'damage_survey'           ? 'selected' : '' }}>Damage Survey</option>
                        <option value="pre_trip_inspection"     {{ request('inquiry_type') === 'pre_trip_inspection'     ? 'selected' : '' }}>Pre-trip Inspection</option>
                        <option value="repair_assessment"       {{ request('inquiry_type') === 'repair_assessment'       ? 'selected' : '' }}>Repair Assessment</option>
                        <option value="condition_survey"        {{ request('inquiry_type') === 'condition_survey'        ? 'selected' : '' }}>Condition Survey</option>
                        <option value="pre_delivery_inspection" {{ request('inquiry_type') === 'pre_delivery_inspection' ? 'selected' : '' }}>Pre-delivery Inspection</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="">All Priority</option>
                        <option value="normal"   {{ request('priority') === 'normal'   ? 'selected' : '' }}>Normal</option>
                        <option value="urgent"   {{ request('priority') === 'urgent'   ? 'selected' : '' }}>Urgent</option>
                        <option value="critical" {{ request('priority') === 'critical' ? 'selected' : '' }}>Critical</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <input type="date" name="date_from" class="form-control form-control-sm"
                               value="{{ request('date_from') }}" title="From date">
                        <span class="input-group-text px-1 text-muted" style="font-size:.75rem;">–</span>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                               value="{{ request('date_to') }}" title="To date">
                    </div>
                </div>
                <div class="col-auto ms-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('surveys.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i>Clear
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Survey Table -->
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Survey No.</th>
                        <th>Container No.</th>
                        <th>Size/Type</th>
                        <th>Customer</th>
                        <th>Survey Type</th>
                        <th>Inspector</th>
                        <th>Date</th>
                        <th>Estimate</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inquiries as $inquiry)
                    @php
                        $statusColors = [
                            'open'          => 'warning text-dark',
                            'in_progress'   => 'primary',
                            'estimate_sent' => 'info',
                            'approved'      => 'success',
                            'closed'        => 'dark',
                        ];
                        $typeLabels = [
                            'damage_survey'          => 'Damage Survey',
                            'pre_trip_inspection'    => 'Pre-trip Inspection',
                            'repair_assessment'      => 'Repair Assessment',
                            'condition_survey'       => 'Condition Survey',
                            'pre_delivery_inspection'=> 'Pre-delivery Inspection',
                        ];
                        $statusLabel = ucwords(str_replace('_', ' ', $inquiry->status));
                        $color = $statusColors[$inquiry->status] ?? 'secondary';
                    @endphp
                    <tr>
                        <td class="ps-3 fw-semibold small">{{ $inquiry->inquiry_no }}</td>
                        <td class="font-monospace fw-semibold small">{{ $inquiry->container_no }}</td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary">
                                {{ $inquiry->size }} {{ $inquiry->type_code }}
                            </span>
                        </td>
                        <td class="small">{{ $inquiry->customer?->name ?? '—' }}</td>
                        <td>
                            <span class="badge bg-light border text-dark">
                                {{ $typeLabels[$inquiry->inquiry_type] ?? $inquiry->inquiry_type }}
                            </span>
                        </td>
                        <td class="small">{{ $inquiry->inspector?->name ?? '—' }}</td>
                        <td class="small text-muted">
                            {{ $inquiry->inspection_date ? \Carbon\Carbon::parse($inquiry->inspection_date)->format('d M Y') : '—' }}
                        </td>
                        <td class="small">
                            @if($inquiry->estimate)
                                <a href="{{ route('estimates.show', $inquiry->estimate) }}"
                                   class="badge bg-primary-subtle text-primary text-decoration-none">
                                    {{ $inquiry->estimate->estimate_no }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge rounded-pill bg-{{ $color }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('surveys.show', $inquiry) }}"
                                   class="btn btn-outline-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @if($inquiry->estimate)
                                <a href="{{ route('estimates.show', $inquiry->estimate) }}"
                                   class="btn btn-outline-warning" title="View Estimate ({{ $inquiry->estimate->estimate_no }})">
                                    <i class="bi bi-receipt"></i>
                                </a>
                                @else
                                <a href="{{ route('estimates.create', ['inquiry_id' => $inquiry->id]) }}"
                                   class="btn btn-outline-warning" title="Create Estimate">
                                    <i class="bi bi-tools"></i>
                                </a>
                                @endif
                                <a href="{{ route('surveys.edit', $inquiry) }}"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if(!$inquiry->estimate)
                                <button type="button" class="btn btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#modalDelete"
                                        data-url="{{ route('surveys.destroy', $inquiry) }}"
                                        data-no="{{ $inquiry->inquiry_no }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                            No surveys found.
                            <a href="{{ route('surveys.create') }}">Create the first one</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">
            Showing {{ $inquiries->firstItem() ?? 0 }}–{{ $inquiries->lastItem() ?? 0 }}
            of {{ $inquiries->total() }} surveys
        </span>
        {{ $inquiries->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="modalDelete" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Survey</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete survey <strong id="deleteSrvNo"></strong>? This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="formDelete" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('modalDelete').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('deleteSrvNo').textContent = btn.dataset.no;
    document.getElementById('formDelete').action = btn.dataset.url;
});
</script>
@endpush
