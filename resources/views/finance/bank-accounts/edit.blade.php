@extends('layouts.app')

@section('title', 'Edit Bank Account')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.bank-accounts.index') }}">Bank Accounts</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-bank2 me-2 text-primary"></i>Edit Bank Account</h4>
        <p class="text-muted mb-0 small">{{ $bankAccount->account_name }} — {{ $bankAccount->bank_name }}</p>
    </div>
    <a href="{{ route('finance.bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card content-card" style="max-width: 700px;">
    <div class="card-body">
        <form method="POST" action="{{ route('finance.bank-accounts.update', $bankAccount) }}">
            @csrf
            @method('PATCH')
            @include('finance.bank-accounts._form')
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Update Bank Account
                </button>
                <a href="{{ route('finance.bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
