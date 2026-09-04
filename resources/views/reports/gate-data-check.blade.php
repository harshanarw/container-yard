@extends('layouts.app')

@section('title', 'Gate Data Check')

@section('breadcrumb')
    <li class="breadcrumb-item">Reports</li>
    <li class="breadcrumb-item active">Gate Data Check</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-shield-exclamation me-2 text-primary"></i>Gate Data Check</h4>
    <p class="text-muted mb-0 small">Gate movements whose dates cannot be right</p>
</div>

{{-- ── Filters ─────────────────────────────────────────────────────────── --}}
<div class="card content-card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('reports.gate-data-check') }}">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from'] }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to'] }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm mb-1">Customer</label>
                    <select name="customer_id" class="form-select form-select-sm select2">
                        <option value="">All customers</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ $filters['customer_id'] == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Apply</button>
                    <a href="{{ route('reports.gate-data-check') }}" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </div>
            {{-- Empty dates are deliberate: a future-dated arrival falls outside
                 any range ending today, so a default range would hide it. --}}
            <div class="form-text small mt-2">
                Leave the dates empty to check everything — a future-dated arrival sits outside any
                range ending today.
            </div>
        </form>
    </div>
</div>

{{-- ── Open findings ───────────────────────────────────────────────────── --}}
<div class="card content-card mb-3">
    <div class="card-header bg-transparent py-2">
        @if($open->isEmpty())
            <strong class="small text-success"><i class="bi bi-check-circle me-1"></i>Nothing to fix</strong>
        @else
            <strong class="small text-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ $open->count() }} need attention
            </strong>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Container</th>
                        <th>Customer</th>
                        <th>Problem</th>
                        <th>Detail</th>
                        <th class="text-end pe-3" style="width:190px">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($open as $f)
                    <tr>
                        <td class="ps-3 font-monospace small">{{ $f['movement']->container_no }}</td>
                        <td class="small">{{ $f['movement']->customer->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $f['check'] === 'no_gate_in' ? 'bg-secondary-subtle text-secondary border border-secondary-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' }}">
                                {{ $f['label'] }}
                            </span>
                        </td>
                        <td class="small">{{ $f['detail'] }}</td>
                        <td class="text-end pe-3">
                            {{-- Fixing happens on the movement edit screen, which
                                 already syncs the corrected date to billing and
                                 refuses a correction that is still wrong. --}}
                            <a href="{{ route('yard.movements.edit', $f['movement']) }}"
                               class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-pencil me-1"></i>Fix
                            </a>
                            @can('gate-check.review')
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#reviewModal"
                                    data-movement="{{ $f['movement']->id }}"
                                    data-check="{{ $f['check'] }}"
                                    data-container="{{ $f['movement']->container_no }}"
                                    data-label="{{ $f['label'] }}">
                                Reviewed
                            </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4 fst-italic">
                            No gate movements with impossible dates in this range.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Reviewed ────────────────────────────────────────────────────────── --}}
@if($reviewed->isNotEmpty())
<div class="card content-card">
    <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
        <strong class="small text-muted">
            <i class="bi bi-check2 me-1"></i>{{ $reviewed->count() }} reviewed and accepted
        </strong>
        <button class="btn btn-link btn-sm p-0" data-bs-toggle="collapse" data-bs-target="#reviewedList">show</button>
    </div>
    <div class="collapse" id="reviewedList">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Container</th>
                        <th>Problem</th>
                        <th>Note</th>
                        <th>Reviewed by</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($reviewed as $f)
                    <tr class="text-muted">
                        <td class="ps-3 font-monospace small">{{ $f['movement']->container_no }}</td>
                        <td class="small">{{ $f['label'] }}</td>
                        <td class="small">{{ $f['review']->note }}</td>
                        <td class="small">
                            {{ $f['review']->reviewer->name ?? '—' }}
                            <span class="d-block text-muted">{{ $f['review']->updated_at->format('d M Y') }}</span>
                        </td>
                        <td class="text-end pe-3">
                            @can('gate-check.review')
                            <form method="POST" action="{{ route('reports.gate-data-check.unreview', $f['movement']) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <input type="hidden" name="check" value="{{ $f['check'] }}">
                                <button class="btn btn-outline-secondary btn-sm">Reopen</button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- ── Review modal ────────────────────────────────────────────────────── --}}
@can('gate-check.review')
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="reviewForm">
            @csrf
            <input type="hidden" name="check" id="reviewCheck">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Mark as reviewed</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        <span class="font-monospace" id="reviewContainer"></span> —
                        <span id="reviewLabel"></span>
                    </p>
                    <label class="form-label form-label-sm">Why is there nothing to correct?</label>
                    <textarea name="note" class="form-control form-control-sm" rows="3" required minlength="5" maxlength="500"
                              placeholder="e.g. Arrival was never recorded, pre-dates the system."></textarea>
                    {{-- The note is required on purpose. This button exists for
                         findings with no right answer; without a reason it would
                         become a way to clear the list without looking. --}}
                    <div class="form-text small">
                        Use this only where the record cannot be corrected. If the date is simply
                        wrong, use <strong>Fix</strong> instead.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Mark reviewed</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

@endsection

@push('scripts')
<script>
document.getElementById('reviewModal')?.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('reviewForm').action =
        "{{ url('reports/gate-data-check') }}/" + btn.dataset.movement + "/review";
    document.getElementById('reviewCheck').value     = btn.dataset.check;
    document.getElementById('reviewContainer').textContent = btn.dataset.container;
    document.getElementById('reviewLabel').textContent     = btn.dataset.label;
});
</script>
@endpush
