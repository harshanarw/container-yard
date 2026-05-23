@extends('layouts.app')

@section('title', 'Edit Container')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('containers.index') }}" class="text-decoration-none">Container Master</a></li>
    <li class="breadcrumb-item"><a href="{{ route('containers.show', $container) }}" class="text-decoration-none">{{ $container->container_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="page-header">
    <h4><i class="bi bi-pencil me-2 text-primary"></i>Edit Container — {{ $container->container_no }}</h4>
    <p class="text-muted mb-0 small">Update master profile for this container</p>
</div>

@include('containers._form', ['container' => $container, 'customers' => $customers, 'equipmentTypes' => $equipmentTypes])

@endsection
