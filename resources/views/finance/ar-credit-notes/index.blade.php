@extends('layouts.app')

@section('title', 'AR Credit Notes')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">AR Credit Notes</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-arrow-counterclockwise me-2 text-primary"></i>AR Credit Notes</h4>
        <p class="text-muted mb-0 small">Credit notes issued to customers (reduce receivables)</p>
    </div>
    @can('finance.ar-credit-notes.create')
    <a href="{{ route('finance.ar-credit-notes.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Credit Note
    </a>
    @endcan
</div>

@if(session('success'))<div class="alert alert-success alert-dismissible fade show py-2 small">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if(session('error'))<div class="alert alert-danger alert-dismissible fade show py-2 small">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif

<div class="card content-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Credit Note</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Applied</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($creditNotes as $cn)
                <tr style="cursor:pointer" onclick="window.location='{{ route('finance.ar-credit-notes.show', $cn) }}'">
                    <td class="font-monospace">{{ $cn->credit_note_no }}</td>
                    <td class="text-muted">{{ $cn->credit_date->format('d M Y') }}</td>
                    <td>{{ $cn->customer->name ?? '—' }}</td>
                    <td class="text-end font-monospace">{{ $cn->currency }} {{ number_format($cn->total_amount, 2) }}</td>
                    <td class="text-end font-monospace text-muted">{{ $cn->currency }} {{ number_format($cn->applied_total, 2) }}</td>
                    <td class="text-center">
                        @php $b = \App\Models\ArCreditNote::statusBadge($cn->status); @endphp
                        <span class="badge bg-{{ $b }}-subtle text-{{ $b }} text-capitalize">{{ $cn->status }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No credit notes yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-2">{{ $creditNotes->links() }}</div>
</div>

@endsection
