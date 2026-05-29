@extends('layouts.app')

@section('title', 'Edit Invoice ' . $invoice->invoice_no)

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('repair-invoices.index') }}">Repair Invoices</a></li>
    <li class="breadcrumb-item"><a href="{{ route('repair-invoices.show', $invoice) }}">{{ $invoice->invoice_no }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')

<div class="page-header mb-4">
    <h4><i class="bi bi-pencil me-2"></i>Edit Invoice {{ $invoice->invoice_no }}</h4>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>
    <strong>Errors:</strong>
    <ul class="mb-0 ms-3">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ route('repair-invoices.update', $invoice) }}" class="row g-4">
    @csrf @method('PATCH')

    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Invoice Details</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Invoice #</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" value="{{ $invoice->invoice_no }}" disabled>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Container</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" value="{{ $invoice->container_no }}" disabled>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Customer</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control" value="{{ $invoice->customer->name }}" disabled>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Grand Total</label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <span class="input-group-text fw-semibold">{{ $invoice->currency }}</span>
                            <input type="text" class="form-control" value="{{ number_format($invoice->grand_total, 2) }}" disabled>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <label for="amount_paid" class="col-sm-3 col-form-label">Amount Paid</label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <span class="input-group-text fw-semibold">{{ $invoice->currency }}</span>
                            <input type="number" step="0.01" min="0" class="form-control @error('amount_paid') is-invalid @enderror"
                                   name="amount_paid" id="amount_paid" value="{{ old('amount_paid', $invoice->amount_paid) }}">
                            @error('amount_paid')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <label class="col-sm-3 col-form-label">Balance Due</label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <span class="input-group-text fw-semibold">{{ $invoice->currency }}</span>
                            <input type="text" class="form-control"
                                   value="{{ number_format(max(0, $invoice->grand_total - ($invoice->amount_paid ?? 0)), 2) }}" disabled>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row mb-3">
                    <label for="notes" class="col-sm-3 col-form-label">Notes</label>
                    <div class="col-sm-9">
                        <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" id="notes" rows="3">{{ old('notes', $invoice->notes) }}</textarea>
                        @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Summary</h5>
            </div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-6">Invoice Date</dt>
                    <dd class="col-6">{{ $invoice->invoice_date->format('d M Y') }}</dd>

                    <dt class="col-6">Due Date</dt>
                    <dd class="col-6">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd>

                    <dt class="col-6">Subtotal</dt>
                    <dd class="col-6 text-end">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</dd>

                    <dt class="col-6">Tax ({{ $invoice->tax_percentage }}%)</dt>
                    <dd class="col-6 text-end">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</dd>

                    <dt class="col-6 fw-bold">Grand Total</dt>
                    <dd class="col-6 text-end fw-bold">{{ $invoice->currency }} {{ number_format($invoice->grand_total, 2) }}</dd>
                </dl>
            </div>
        </div>

        <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check me-1"></i>Save
            </button>
            <a href="{{ route('repair-invoices.show', $invoice) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
        </div>
    </div>
</form>

@endsection
