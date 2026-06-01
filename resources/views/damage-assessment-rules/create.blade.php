@extends('layouts.app')

@section('title', 'New Assessment Rule')

@section('breadcrumb')
    <li class="breadcrumb-item">Setup</li>
    <li class="breadcrumb-item">Inspection</li>
    <li class="breadcrumb-item"><a href="{{ route('masters.damage-assessment-rules.index') }}">Assessment Rules</a></li>
    <li class="breadcrumb-item active">New Rule</li>
@endsection

@section('content')

<div class="page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4><i class="bi bi-journal-plus me-2 text-primary"></i>New Assessment Rule</h4>
        <p class="text-muted small mb-0">Define a reusable Location / Component / Damage / Repair combination</p>
    </div>
    <a href="{{ route('masters.damage-assessment-rules.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1 ps-3">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('masters.damage-assessment-rules.store') }}">
                    @csrf
                    @include('damage-assessment-rules._form', ['rule' => null])
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Rule
                        </button>
                        <a href="{{ route('masters.damage-assessment-rules.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
