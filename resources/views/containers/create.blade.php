@extends('layouts.app')

@section('title', 'Add Container')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('containers.index') }}" class="text-decoration-none">Container Master</a></li>
    <li class="breadcrumb-item active">Add Container</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-plus-circle me-2 text-primary"></i>Add Container to Master</h4>
    <p class="text-muted mb-0 small">Register a new container profile in the master registry</p>
</div>

@include('containers._form', ['container' => null, 'customers' => $customers, 'equipmentTypes' => $equipmentTypes])

@endsection
