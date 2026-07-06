@extends('layouts.app')

@section('title', 'New Container Booking')

@section('breadcrumb')
    <li class="breadcrumb-item">Containers</li>
    <li class="breadcrumb-item"><a href="{{ route('container-bookings.index') }}">Bookings</a></li>
    <li class="breadcrumb-item active">New</li>
@endsection

@section('content')

<div class="page-header">
    <h4 class="mb-0"><i class="bi bi-journal-plus me-2 text-primary"></i>New Container Booking</h4>
    <p class="text-muted mb-0 small">A booking / EDO can carry several size · type lines, each with a quantity.</p>
</div>

@if($errors->any())<div class="alert alert-danger py-2 small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('container-bookings.store') }}">
    @csrf
    <div class="card content-card mb-3">
        <div class="card-header bg-transparent py-2"><strong class="small">Booking Details</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Booking No / EDO <span class="text-danger">*</span></label>
                    <input type="text" name="booking_no" class="form-control form-control-sm" value="{{ old('booking_no') }}" required maxlength="60">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Shipping Line <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select form-select-sm select2" required>
                        <option value="">— Select shipping line —</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label fw-semibold small">Valid From</label>
                    <input type="date" name="valid_from" class="form-control form-control-sm" value="{{ old('valid_from') }}">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label fw-semibold small">Valid To</label>
                    <input type="date" name="valid_to" class="form-control form-control-sm" value="{{ old('valid_to') }}">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Remarks</label>
                    <input type="text" name="remarks" class="form-control form-control-sm" value="{{ old('remarks') }}" maxlength="1000">
                </div>
            </div>
        </div>
    </div>

    <div class="card content-card">
        <div class="card-header bg-transparent py-2 d-flex justify-content-between align-items-center">
            <strong class="small">Booking Lines</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add line</button>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr><th style="width:20%">Size</th><th style="width:25%">Type</th><th style="width:30%">Grade</th><th class="text-end" style="width:15%">Quantity</th><th style="width:36px"></th></tr>
                </thead>
                <tbody id="linesBody"></tbody>
            </table>
        </div>
        <div class="card-footer bg-transparent text-end">
            <a href="{{ route('container-bookings.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Create Booking</button>
        </div>
    </div>
</form>

<template id="lineTpl">
    <tr>
        <td>
            <select name="lines[IDX][size]" class="form-select form-select-sm" required>
                <option value="">Size</option>
                @foreach($equipmentTypes->pluck('size')->unique()->sort() as $sz)
                    <option value="{{ $sz }}">{{ $sz }}ft</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="lines[IDX][type_code]" class="form-select form-select-sm" required>
                <option value="">Type</option>
                @foreach($equipmentTypes->pluck('type_code')->unique()->sort() as $tc)
                    <option value="{{ $tc }}">{{ $tc }}</option>
                @endforeach
            </select>
        </td>
        <td>
            <select name="lines[IDX][grade_id]" class="form-select form-select-sm">
                <option value="">Any grade</option>
                @foreach($grades as $g)
                    <option value="{{ $g->id }}">{{ $g->code }} — {{ $g->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input type="number" name="lines[IDX][quantity]" class="form-control form-control-sm text-end font-monospace" min="1" value="1" required></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rm-line"><i class="bi bi-x"></i></button></td>
    </tr>
</template>

@push('scripts')
<script>
(function () {
    const body = document.getElementById('linesBody');
    const tpl  = document.getElementById('lineTpl').innerHTML;
    let idx = 0;
    function addLine() { body.insertAdjacentHTML('beforeend', tpl.replace(/IDX/g, idx++)); }
    document.getElementById('addLine').addEventListener('click', addLine);
    body.addEventListener('click', e => { if (e.target.closest('.rm-line')) e.target.closest('tr').remove(); });
    addLine(); // start with one row
})();
</script>
@endpush
@endsection
