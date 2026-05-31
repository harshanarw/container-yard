@extends('layouts.app')

@section('title', 'M&R Rate Tariff')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item active">M&amp;R Tariff</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-tools me-2 text-primary"></i>M&amp;R Rate Tariff</h4>
        <p class="text-muted mb-0 small">
            Define standard maintenance &amp; repair rates per owner/operator. Used to auto-populate estimate lines.
        </p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-circle me-1"></i>New Tariff
    </button>
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

{{-- ── Stats strip ── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-primary">{{ $tariffs->count() }}</div>
            <div class="text-muted small">Total Tariffs</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-success">{{ $tariffs->where('is_active', true)->count() }}</div>
            <div class="text-muted small">Active</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-secondary">{{ $tariffs->where('is_active', false)->count() }}</div>
            <div class="text-muted small">Inactive</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card content-card text-center py-3">
            <div class="fs-3 fw-bold text-info">{{ $tariffs->pluck('rules_count')->sum() }}</div>
            <div class="text-muted small">Total Rules</div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-header d-flex align-items-center justify-content-between py-2">
        <span><i class="bi bi-table me-2 text-primary"></i>All M&amp;R Tariffs</span>
        <span class="badge bg-primary-subtle text-primary">{{ $tariffs->count() }} record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:50px;">#</th>
                        <th>Tariff Name</th>
                        <th>Owner / Customer</th>
                        <th style="width:200px;">Validity Period</th>
                        <th style="width:70px;" class="text-center">Cur.</th>
                        <th style="width:80px;" class="text-center">Rules</th>
                        <th style="width:90px;" class="text-center">Status</th>
                        <th style="width:110px;" class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($tariffs as $tariff)
                    @php
                        $now     = now();
                        $expired = $tariff->valid_to && $tariff->valid_to->lt($now);
                        $rowCls  = !$tariff->is_active ? 'table-secondary text-muted' : ($expired ? 'table-warning' : '');
                    @endphp
                    <tr class="{{ $rowCls }}">
                        <td class="ps-3 text-muted small">{{ $tariff->id }}</td>
                        <td class="fw-semibold small">{{ $tariff->name }}</td>
                        <td class="small">{!! $tariff->customer->name ?? '<span class="text-muted fst-italic">Default / All</span>' !!}</td>
                        <td class="small">
                            {{ $tariff->valid_from->format('d M Y') }}
                            @if($tariff->valid_to)
                                – {{ $tariff->valid_to->format('d M Y') }}
                                @if($expired)
                                    <span class="badge bg-warning text-dark ms-1">Expired</span>
                                @endif
                            @else
                                – <em class="text-muted">open-ended</em>
                            @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary font-monospace">{{ $tariff->currency }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill">{{ $tariff->rules_count }}</span>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="{{ route('masters.mr-tariff.toggle', $tariff) }}">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="btn btn-sm {{ $tariff->is_active ? 'btn-success' : 'btn-outline-secondary' }}"
                                        title="{{ $tariff->is_active ? 'Active' : 'Inactive' }}">
                                    <i class="bi {{ $tariff->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                </button>
                            </form>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('masters.mr-tariff.show', $tariff) }}"
                                   class="btn btn-sm btn-outline-info" title="View / Edit Rules">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete"
                                        data-id="{{ $tariff->id }}"
                                        data-label="{{ $tariff->name }}"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-tools fs-3 d-block mb-2"></i>
                            No M&amp;R tariffs yet. Click <strong>New Tariff</strong> to create one.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Add Modal ── --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ route('masters.mr-tariff.store') }}">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h6 class="modal-title"><i class="bi bi-plus-circle me-1 text-primary"></i>New M&amp;R Tariff</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tariff Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100"
                               placeholder="e.g. MSC Standard 2025">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Owner / Customer</label>
                        <select name="customer_id" class="form-select s2-code">
                            <option value="">— Default / All Customers —</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-code="{{ $c->code }}" data-name="{{ $c->name }}">{{ $c->code }} — {{ $c->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Leave blank to use as a fallback for all owners.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Valid From <span class="text-danger">*</span></label>
                            <input type="date" name="valid_from" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Valid To</label>
                            <input type="date" name="valid_to" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Currency <span class="text-danger">*</span></label>
                            <input type="text" name="currency" class="form-control text-uppercase font-monospace"
                                   maxlength="3" required value="USD" oninput="this.value=this.value.toUpperCase()">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Applicable Sizes</label>
                        <div class="d-flex gap-3">
                            @foreach(['20', '40', '45'] as $sz)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="applicable_sizes[]"
                                       value="{{ $sz }}" id="sz{{ $sz }}" checked>
                                <label class="form-check-label" for="sz{{ $sz }}">{{ $sz }}'</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Create &amp; Add Rules
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Delete Modal ── --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Delete Tariff</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-1">
                <p class="small mb-0">Delete tariff <strong id="deleteLabel"></strong> and all its rules?
                   This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST">
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
document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('deleteLabel').textContent = btn.dataset.label;
        document.getElementById('deleteForm').action =
            '{{ url("masters/mr-tariff") }}/' + btn.dataset.id;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    });
});
</script>
@endpush
