@extends('layouts.app')

@section('title', 'Edit Supplier')

@section('breadcrumb')
    <li class="breadcrumb-item">Finance</li>
    <li class="breadcrumb-item"><a href="{{ route('finance.suppliers.index') }}">Suppliers</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-truck me-2 text-primary"></i>Edit Supplier — {{ $supplier->name }}</h4>
</div>

<form method="POST" action="{{ route('finance.suppliers.update', $supplier) }}">
    @csrf
    @method('PATCH')
    @include('finance.suppliers._form')

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
        <a href="{{ route('finance.suppliers.show', $supplier) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
        @can('finance.suppliers.delete')
        @if(!$supplier->invoices()->exists())
        <button type="submit" form="delete-supplier" class="btn btn-outline-danger btn-sm ms-auto"
                onclick="return confirm('Delete this supplier?')"><i class="bi bi-trash me-1"></i>Delete</button>
        @endif
        @endcan
    </div>
</form>

@can('finance.suppliers.delete')
<form id="delete-supplier" method="POST" action="{{ route('finance.suppliers.destroy', $supplier) }}" class="d-none">
    @csrf @method('DELETE')
</form>
@endcan

@endsection
