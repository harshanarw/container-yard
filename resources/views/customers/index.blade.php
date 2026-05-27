@extends('layouts.app')

@section('title', 'Customers')

@section('breadcrumb')
    <li class="breadcrumb-item active">Customers</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between">
    <div>
        <h4><i class="bi bi-person-badge me-2 text-primary"></i>Customer Registry</h4>
        <p class="text-muted mb-0 small">Manage shipping lines and container owners</p>
    </div>
    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>Register Customer
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<!-- Summary Cards -->
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
                <div><div class="text-muted small">Total Customers</div><div class="fs-5 fw-bold">{{ $totalCustomers }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-success-subtle text-success"><i class="bi bi-check2-circle"></i></div>
                <div><div class="text-muted small">Active Customers</div><div class="fs-5 fw-bold">{{ $activeCustomers }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card">
            <div class="card-body py-3 d-flex align-items-center gap-3">
                <div class="card-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
                <div><div class="text-muted small">Pending Verification</div><div class="fs-5 fw-bold">{{ $pendingCustomers }}</div></div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="{{ route('customers.index') }}">
<div class="card content-card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search customer name, code…"
                           value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="type_id" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($customerTypes as $ct)
                        <option value="{{ $ct->id }}" {{ request('type_id') == $ct->id ? 'selected' : '' }}>
                            {{ $ct->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="active"   {{ request('status')=='active'  ?'selected':'' }}>Active</option>
                    <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>Inactive</option>
                    <option value="pending"  {{ request('status')=='pending' ?'selected':'' }}>Pending</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="{{ route('customers.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
            <div class="col-auto ms-md-auto">
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Export</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<!-- Customer Table -->
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="customerTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Code</th>
                        <th>Customer Name</th>
                        <th>Type</th>
                        <th>Contact Person</th>
                        <th>Phone / Email</th>
                        <th class="text-center">Containers</th>
                        <th>Credit Limit</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    <tr>
                        <td class="ps-3">
                            <span class="badge bg-dark text-white font-monospace">{{ $customer->code }}</span>
                        </td>
                        <td class="fw-semibold small">
                            {{ $customer->name }}
                            @if($customer->tax_exempt)
                                <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;" title="Tax Exempt">
                                    <i class="bi bi-shield-check"></i> Exempt
                                </span>
                            @endif
                        </td>
                        <td>
                            @foreach($customer->types->take(2) as $t)
                                <span class="badge bg-info-subtle text-info border border-info-subtle me-1">{{ $t->name }}</span>
                            @endforeach
                            @if($customer->types->count() > 2)
                                <span class="badge bg-secondary">+{{ $customer->types->count() - 2 }}</span>
                            @endif
                            @if($customer->types->isEmpty())
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="small">{{ $customer->contact_person }}</td>
                        <td class="small text-muted">
                            {{ $customer->phone_office }}<br>
                            <a href="mailto:{{ $customer->email }}" class="text-decoration-none">{{ $customer->email }}</a>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill">{{ $customer->containers_count }}</span>
                        </td>
                        <td class="small">{{ $customer->currency }} {{ number_format($customer->credit_limit) }}</td>
                        <td>
                            @php $sc = $customer->status==='active' ? 'success' : ($customer->status==='pending' ? 'warning text-dark' : 'secondary'); @endphp
                            <span class="badge rounded-pill bg-{{ $sc }}">{{ ucfirst($customer->status) }}</span>
                        </td>
                        <td class="text-end pe-3">
                            <div class="d-flex flex-wrap justify-content-end gap-1">
                                <a href="{{ route('customers.show', $customer) }}"
                                   class="btn btn-outline-info btn-sm" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('customers.edit', $customer) }}"
                                   class="btn btn-outline-primary btn-sm" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm btn-delete-customer"
                                        title="Delete Customer"
                                        data-name="{{ $customer->name }}"
                                        data-url="{{ route('customers.destroy', $customer) }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalDeleteCustomer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>No customers found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
        <span class="text-muted small">
            @if($customers->total() > 0)
                Showing {{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ $customers->total() }} customers
            @else
                No customers found
            @endif
        </span>
        {{ $customers->links('pagination::bootstrap-5') }}
    </div>
</div>

{{-- ══════════════════ MODAL: DELETE CUSTOMER ══════════════════ --}}
<div class="modal fade" id="modalDeleteCustomer" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="formDeleteCustomer" action="">
                @csrf
                @method('DELETE')
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>Delete Customer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-2">
                    <i class="bi bi-trash text-danger" style="font-size:3rem;"></i>
                    <p class="mt-2 mb-0">
                        Delete <strong id="deleteCustomerName"></strong>?<br>
                        <span class="small text-muted">This action cannot be undone.</span>
                    </p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function () {
    $('#modalDeleteCustomer').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        $('#deleteCustomerName').text(btn.data('name'));
        $('#formDeleteCustomer').attr('action', btn.data('url'));
    });
});
</script>
@endpush
