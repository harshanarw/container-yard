@extends('layouts.app')

@section('title', 'Customer — ' . $customer->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}">Customers</a></li>
    <li class="breadcrumb-item active">{{ $customer->code }}</li>
@endsection

@section('content')

@php
$typeLabels = [
    'shipping_line'     => 'Shipping Line',
    'freight_forwarder' => 'Freight Forwarder',
    'depot_owner'       => 'Depot Owner',
    'nvo_carrier'       => 'NVO Carrier',
    'leasing_company'   => 'Leasing Company',
];
$paymentLabels = [
    'cod'   => 'Cash on Delivery',
    'net15' => 'Net 15 Days',
    'net30' => 'Net 30 Days',
    'net45' => 'Net 45 Days',
    'net60' => 'Net 60 Days',
];
$statusColor = $customer->status === 'active' ? 'success' : ($customer->status === 'pending' ? 'warning text-dark' : 'secondary');
@endphp

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>{{ $customer->name }}</h4>
        <p class="text-muted mb-0 small">
            <span class="badge bg-dark font-monospace me-1">{{ $customer->code }}</span>
            {{ $typeLabels[$customer->type] ?? $customer->type }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<!-- Stats Row -->
<div class="row g-3 mb-3">
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-primary">{{ $customer->containers_count }}</div>
            <div class="text-muted small">Containers</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-info">{{ $customer->inquiries_count }}</div>
            <div class="text-muted small">Inquiries</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-success">{{ $customer->estimates_count }}</div>
            <div class="text-muted small">Estimates</div>
        </div>
    </div>
    <div class="col-sm-3">
        <div class="card stat-card text-center py-3">
            <div class="fs-4 fw-bold text-warning">{{ $customer->gate_movements_count }}</div>
            <div class="text-muted small">Gate Movements</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left Column -->
    <div class="col-lg-8">

        <!-- Company Information -->
        <div class="card content-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-building me-2 text-primary"></i>Company Information</span>
                <span class="badge rounded-pill bg-{{ $statusColor }}">{{ ucfirst($customer->status) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Customer Code</div>
                        <div class="fw-semibold font-monospace">{{ $customer->code }}</div>
                    </div>
                    <div class="col-md-8">
                        <div class="text-muted small">Company Name</div>
                        <div class="fw-semibold">{{ $customer->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Type</div>
                        <div>{{ $typeLabels[$customer->type] ?? $customer->type }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Registration No. (SSM)</div>
                        <div>{{ $customer->registration_no ?: '—' }}</div>
                    </div>
                    <div class="col-12">
                        <div class="text-muted small">Address</div>
                        <div>{{ $customer->address ?: '—' }}</div>
                        @if($customer->city || $customer->state)
                        <div class="text-muted small mt-1">{{ implode(', ', array_filter([$customer->city, $customer->state, $customer->country])) }}</div>
                        @endif
                    </div>
                    @if($customer->website)
                    <div class="col-12">
                        <div class="text-muted small">Website</div>
                        <a href="{{ $customer->website }}" target="_blank" class="small">{{ $customer->website }}</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-telephone me-2 text-primary"></i>Contact Information
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted small">Contact Person</div>
                        <div class="fw-semibold">{{ $customer->contact_person }}</div>
                        @if($customer->designation)
                        <div class="text-muted small">{{ $customer->designation }}</div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Email</div>
                        <a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Office Phone</div>
                        <div>{{ $customer->phone_office }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Mobile</div>
                        <div>{{ $customer->phone_mobile ?: '—' }}</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Fax</div>
                        <div>{{ $customer->fax ?: '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Containers -->
        @if($recentContainers->isNotEmpty())
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-box-seam me-2 text-primary"></i>Recent Containers
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Container No.</th>
                            <th>Size/Type</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentContainers as $container)
                        <tr>
                            <td class="ps-3 font-monospace small">{{ $container->container_no ?? '—' }}</td>
                            <td class="small">{{ $container->size ?? '—' }}</td>
                            <td class="small">{{ ucfirst($container->status ?? '—') }}</td>
                            <td class="small text-muted">{{ $container->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    <!-- Right Column -->
    <div class="col-lg-4">

        <!-- Billing & Rates -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-cash-stack me-2 text-primary"></i>Billing & Rates
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Currency</div>
                    <div class="fw-semibold">{{ $customer->currency }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Credit Limit</div>
                    <div class="fw-semibold">{{ $customer->currency }} {{ number_format($customer->credit_limit, 2) }}</div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Payment Terms</div>
                    <div>{{ $paymentLabels[$customer->payment_terms] ?? $customer->payment_terms }}</div>
                </div>
                <hr class="my-2">
                @php $tariff = $customer->activeTariff; @endphp
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small">Storage Tariff</span>
                    <a href="{{ route('masters.storage-tariff.index') }}"
                       class="btn btn-xs btn-outline-primary btn-sm py-0 px-2"
                       style="font-size:.72rem;" title="Manage Storage Tariffs">
                        <i class="bi bi-calendar2-range me-1"></i>Manage
                    </a>
                </div>
                @if($tariff)
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Free Days</span>
                        <span class="fw-semibold">{{ $tariff->default_free_days }} days</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Validity</span>
                        <span>{{ $tariff->valid_from->format('d M Y') }}
                            @if($tariff->valid_to) — {{ $tariff->valid_to->format('d M Y') }}
                            @else <span class="text-muted">open-ended</span>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Rate Lines</span>
                        <a href="{{ route('masters.storage-tariff.show', $tariff) }}"
                           class="fw-semibold text-decoration-none">
                            {{ $tariff->details->count() }} type(s)
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                @else
                    <div class="text-muted small fst-italic">
                        <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                        No active storage tariff defined.
                        <a href="{{ route('masters.storage-tariff.index') }}" class="text-decoration-none">
                            Add one &rarr;
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <!-- Contract -->
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-file-earmark-check me-2 text-primary"></i>Contract
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <div class="text-muted small">Contract Start</div>
                    <div>{{ $customer->contract_start?->format('d M Y') ?? '—' }}</div>
                </div>
                <div class="mb-2">
                    <div class="text-muted small">Contract End</div>
                    <div>{{ $customer->contract_end?->format('d M Y') ?? '—' }}</div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between small mb-1">
                    <span>Email Notifications</span>
                    <span class="badge bg-{{ $customer->email_notifications ? 'success' : 'secondary' }}">
                        {{ $customer->email_notifications ? 'On' : 'Off' }}
                    </span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span>Auto Invoice</span>
                    <span class="badge bg-{{ $customer->auto_invoice ? 'success' : 'secondary' }}">
                        {{ $customer->auto_invoice ? 'On' : 'Off' }}
                    </span>
                </div>
            </div>
        </div>

        @if($customer->notes)
        <div class="card content-card mb-3">
            <div class="card-header">
                <i class="bi bi-sticky me-2 text-primary"></i>Notes
            </div>
            <div class="card-body small">{{ $customer->notes }}</div>
        </div>
        @endif

        <!-- Delete -->
        <form action="{{ route('customers.destroy', $customer) }}" method="POST"
              onsubmit="return confirm('Delete {{ addslashes($customer->name) }}? This cannot be undone.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                <i class="bi bi-trash me-1"></i>Delete Customer
            </button>
        </form>

    </div>
</div>

@endsection
