@extends('layouts.app')

@section('title', 'Bank Accounts')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item active">Bank Accounts</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-bank2 me-2 text-primary"></i>Bank Accounts</h4>
        <p class="text-muted mb-0 small">Manage bank and cash accounts linked to GL accounts</p>
    </div>
    @can('finance.receipts.create')
    <a href="{{ route('finance.bank-accounts.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Bank Account
    </a>
    @endcan
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Account Name</th>
                        <th>Bank</th>
                        <th>Account No</th>
                        <th>Currency</th>
                        <th>GL Account</th>
                        <th class="text-center">Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bankAccounts as $ba)
                    <tr class="{{ !$ba->is_active ? 'opacity-50' : '' }}">
                        <td class="fw-semibold small">{{ $ba->account_name }}</td>
                        <td class="small">{{ $ba->bank_name }}</td>
                        <td class="font-monospace small">{{ $ba->account_number }}</td>
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $ba->currency }}</span></td>
                        <td class="small">
                            @if($ba->glAccount)
                                <span class="font-monospace text-muted">{{ $ba->glAccount->code }}</span>
                                {{ $ba->glAccount->name }}
                            @else
                                <span class="text-muted fst-italic">Not linked</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <i class="bi bi-{{ $ba->is_active ? 'check-circle-fill text-success' : 'x-circle-fill text-muted' }}"></i>
                        </td>
                        <td class="text-end">
                            @can('finance.receipts.edit')
                            <a href="{{ route('finance.bank-accounts.edit', $ba) }}" class="btn btn-sm btn-outline-secondary py-0 px-2">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                            @can('finance.receipts.delete')
                            <form method="POST" action="{{ route('finance.bank-accounts.destroy', $ba) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                                        onclick="return confirm('Delete bank account {{ addslashes($ba->account_name) }}?')">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </form>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5 small">
                            <i class="bi bi-bank2 d-block fs-2 mb-2 opacity-25"></i>
                            No bank accounts yet. <a href="{{ route('finance.bank-accounts.create') }}">Add one</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($bankAccounts->hasPages())
    <div class="card-footer bg-transparent">{{ $bankAccounts->links() }}</div>
    @endif
</div>

@endsection
