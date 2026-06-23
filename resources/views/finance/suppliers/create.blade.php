@extends('layouts.app')

@section('title', 'New Supplier')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header mb-3">
    <h4 class="mb-0"><i class="bi bi-truck me-2 text-primary"></i>New Supplier</h4>
</div>

<form method="POST" action="{{ route('finance.suppliers.store') }}">
    @csrf
    @include('finance.suppliers._form')

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Create Supplier</button>
        <a href="{{ route('finance.suppliers.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
</form>

@endsection
