@extends('layouts.app')

@section('title', 'Cargo Transfers')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item active">Cargo Transfers</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Cargo Rental / Container Substitution</h4>
    <p class="text-muted mb-0 small">
        Transfer cargo from a customer's laden box into a yard-owned or on-hired box, gate the empty box out
        (stops detention), and bill the customer storage on the substitute box — all under one job.
    </p>
</div>

{{-- Pending: cargo-rental gate-ins awaiting a transfer --}}
<div class="card content-card mb-4">
    <div class="card-header py-2 fw-semibold small">
        <i class="bi bi-hourglass-split me-2 text-warning"></i>Awaiting Cargo Transfer
    </div>
    <div class="card-body p-0">
        @if($pending->isEmpty())
            <div class="p-3 text-muted small">No cargo-rental gate-ins are waiting for a transfer.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Job No</th>
                            <th>Source Box</th>
                            <th>Customer</th>
                            <th>Gated In</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $m)
                            <tr>
                                <td class="font-monospace small">{{ $m->yardJob?->job_no ?? '—' }}</td>
                                <td class="font-monospace fw-semibold">{{ $m->container_no }}</td>
                                <td>{{ $m->customer?->name ?? '—' }}</td>
                                <td class="small">{{ $m->gate_in_time?->format('d M Y') ?? $m->created_at->format('d M Y') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('yard.cargo-transfers.create', $m) }}" class="btn btn-sm btn-primary">
                                        <i class="bi bi-arrow-left-right me-1"></i>Transfer Cargo
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- Recorded transfers --}}
<div class="card content-card">
    <div class="card-header py-2 fw-semibold small">
        <i class="bi bi-list-check me-2 text-primary"></i>Recorded Transfers
    </div>
    <div class="card-body p-0">
        @if($transfers->isEmpty())
            <div class="p-3 text-muted small">No cargo transfers recorded yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Job No</th>
                            <th>Customer</th>
                            <th>Source → Substitute</th>
                            <th>Substitute Source</th>
                            <th>Transfer Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transfers as $t)
                            <tr>
                                <td class="font-monospace small">{{ $t->yardJob?->job_no ?? '—' }}</td>
                                <td>{{ $t->customer?->name ?? '—' }}</td>
                                <td class="font-monospace small">
                                    {{ $t->sourceContainer?->container_no ?? '—' }}
                                    <i class="bi bi-arrow-right mx-1 text-muted"></i>
                                    <span class="fw-semibold">{{ $t->substituteContainer?->container_no ?? '—' }}</span>
                                    @if($t->is_reefer)<span class="badge bg-info-subtle text-info ms-1">Reefer</span>@endif
                                </td>
                                <td class="small">{{ $t->substitute_source === 'on_hired' ? 'On-hired' : 'Yard-owned' }}</td>
                                <td class="small">{{ $t->transfer_date?->format('d M Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $t->status === 'active' ? 'success' : ($t->status === 'completed' ? 'secondary' : 'danger') }}-subtle text-{{ $t->status === 'active' ? 'success' : ($t->status === 'completed' ? 'secondary' : 'danger') }}">
                                        {{ ucfirst($t->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('yard.cargo-transfers.show', $t) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-2">{{ $transfers->links() }}</div>
        @endif
    </div>
</div>

@endsection
