@extends('layouts.app')

@section('title', 'Lessor On-Hire')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('yard.index') }}">Yard</a></li>
    <li class="breadcrumb-item active">Lessor On-Hire</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><i class="bi bi-box-arrow-in-down-right me-2 text-primary"></i>Lessor On-Hire <span class="text-muted fw-normal">(yard as lessee)</span></h4>
        <p class="text-muted mb-0 small">Boxes taken on hire from a shipping line / lessor. Each is a costed job — tag the lessor fee to it, and its P&amp;L shows the margin.</p>
    </div>
    @can('yard.lessor-hire.create')
    <a href="{{ route('yard.lessor-hires.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New On-Hire</a>
    @endcan
</div>

<div class="card content-card">
    <div class="card-body p-0">
        @if($hires->isEmpty())
            <div class="p-3 text-muted small">No lessor on-hires recorded yet.</div>
        @else
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Job No</th><th>Container</th><th>Lessor</th><th>On-Hire</th><th>Off-Hire</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($hires as $h)
                    <tr>
                        <td class="font-monospace small">{{ $h->yardJob?->job_no ?? '—' }}</td>
                        <td class="font-monospace fw-semibold">{{ $h->container?->container_no ?? '—' }}</td>
                        <td>{{ $h->lessor?->name ?? '—' }}</td>
                        <td class="small">{{ $h->on_hire_date?->format('d M Y') }}</td>
                        <td class="small">{{ $h->off_hire_date?->format('d M Y') ?? '—' }}</td>
                        <td><span class="badge bg-{{ $h->status === 'active' ? 'success' : ($h->status === 'completed' ? 'secondary' : 'danger') }}-subtle text-{{ $h->status === 'active' ? 'success' : ($h->status === 'completed' ? 'secondary' : 'danger') }}">{{ ucfirst($h->status) }}</span></td>
                        <td class="text-end"><a href="{{ route('yard.lessor-hires.show', $h) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-2">{{ $hires->links() }}</div>
        @endif
    </div>
</div>

@endsection
