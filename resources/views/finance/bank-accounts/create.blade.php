@extends('layouts.app')

@section('title', 'New Bank Account')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.bank-accounts.index') }}">Bank Accounts</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h4><i class="bi bi-bank2 me-2 text-primary"></i>New Bank Account</h4>
        <p class="text-muted mb-0 small">Add a bank or cash account linked to a GL account</p>
    </div>
    <a href="{{ route('finance.bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="card content-card" style="max-width: 700px;">
    <div class="card-body">
        <form method="POST" action="{{ route('finance.bank-accounts.store') }}">
            @csrf
            @include('finance.bank-accounts._form')
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Save Bank Account
                </button>
                <a href="{{ route('finance.bank-accounts.index') }}" class="btn btn-outline-secondary btn-sm ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

@endsection
