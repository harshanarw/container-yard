@extends('layouts.app')

@section('title', 'New Work Order')

@section('breadcrumb')
    <li class="breadcrumb-item">Operations</li>
    <li class="breadcrumb-item">M&R</li>
    <li class="breadcrumb-item"><a href="{{ route('work-orders.index') }}">Work Orders</a></li>
    <li class="breadcrumb-item active">New Work Order</li>
@endsection

@section('content')

<div class="page-header mb-4">
    <h4><i class="bi bi-hammer me-2 text-primary"></i>New Work Order</h4>
    <p class="text-muted small mb-0">Create a work order from an approved estimate — one per repair category</p>
</div>

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <i class="bi bi-exclamation-circle me-1"></i>
    <ul class="mb-0 ms-2">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if($approvedEstimates->isEmpty())
<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    No approved estimates with unassigned line items available.
    <a href="{{ route('estimates.index') }}" class="alert-link">Go to Estimates</a> to approve one first.
</div>
@else

<form method="POST" action="{{ route('work-orders.store') }}" class="row g-4" id="woForm">
    @csrf

    <div class="col-md-8">

        {{-- Step 1: Estimate --}}
        <div class="card mb-3">
            <div class="card-header bg-light d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:22px;height:22px;">1</span>
                <h6 class="mb-0">Select Estimate</h6>
            </div>
            <div class="card-body">
                <label for="estimate_id" class="form-label fw-semibold">Approved Estimate <span class="text-danger">*</span></label>
                <select class="form-select s2-code @error('estimate_id') is-invalid @enderror"
                        name="estimate_id" id="estimate_id" required>
                    <option value="">— Select an approved estimate —</option>
                    @foreach($approvedEstimates as $est)
                    <option value="{{ $est->id }}"
                            data-code="{{ $est->estimate_no }}"
                            data-name="{{ $est->container_no }} — {{ $est->customer->code ?? $est->customer->name }}"
                            data-categories-url="{{ route('work-orders.available-categories', $est) }}"
                            {{ old('estimate_id', $preselectedEstimateId ?? '') == $est->id ? 'selected' : '' }}>
                        {{ $est->estimate_no }} — {{ $est->container_no }} — {{ $est->customer->code ?? $est->customer->name }}
                        ({{ $est->currency }} {{ number_format($est->grand_total, 2) }})
                    </option>
                    @endforeach
                </select>
                @error('estimate_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Step 2: Category --}}
        <div class="card mb-3 d-none" id="categoryCard">
            <div class="card-header bg-light d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:22px;height:22px;">2</span>
                <h6 class="mb-0">Select Repair Category</h6>
            </div>
            <div class="card-body">
                <div id="categoryLoading" class="text-muted small d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span>Loading categories…
                </div>
                <div id="categoryButtons" class="d-flex flex-wrap gap-2 mb-2"></div>
                <input type="hidden" name="repair_category_id" id="repair_category_id" value="{{ old('repair_category_id') }}">
                @error('repair_category_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div id="uncatWarning" class="alert alert-warning py-2 small mt-2 d-none">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span id="uncatText"></span>
                    <a href="{{ route('masters.repair-category-mappings.index') }}" class="alert-link" target="_blank">Manage mapping rules</a>
                </div>
            </div>
        </div>

        {{-- Step 3: Line Preview --}}
        <div class="card mb-3 d-none" id="previewCard">
            <div class="card-header bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:22px;height:22px;">3</span>
                    <h6 class="mb-0">Lines Included in this Work Order</h6>
                </div>
                <span id="previewCount" class="badge bg-secondary small"></span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Location</th>
                            <th>Component</th>
                            <th>Damage</th>
                            <th>Repair</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end pe-3">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="previewBody"></tbody>
                </table>
            </div>
        </div>

        {{-- Step 4: Work Order Details --}}
        <div class="card d-none" id="detailsCard">
            <div class="card-header bg-light d-flex align-items-center gap-2">
                <span class="badge bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:22px;height:22px;">4</span>
                <h6 class="mb-0">Work Order Details</h6>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="priority" class="form-label fw-semibold">Priority</label>
                        <select class="form-select @error('priority') is-invalid @enderror" name="priority" id="priority">
                            @foreach($priorities as $p)
                            <option value="{{ $p }}" {{ old('priority', 'normal') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="assigned_to" class="form-label fw-semibold">Assigned To</label>
                        <select class="form-select select2 @error('assigned_to') is-invalid @enderror" name="assigned_to" id="assigned_to">
                            <option value="">— Unassigned —</option>
                            @foreach($supervisors as $sup)
                            <option value="{{ $sup->id }}" {{ old('assigned_to') == $sup->id ? 'selected' : '' }}>
                                {{ $sup->name }} ({{ ucfirst(str_replace('_', ' ', $sup->role)) }})
                            </option>
                            @endforeach
                        </select>
                        @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label for="target_date" class="form-label fw-semibold">Target Completion Date</label>
                    <input type="date" class="form-control @error('target_date') is-invalid @enderror"
                           name="target_date" id="target_date" value="{{ old('target_date') }}">
                    @error('target_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-0">
                    <label for="instructions" class="form-label fw-semibold">Instructions</label>
                    <textarea class="form-control @error('instructions') is-invalid @enderror"
                              name="instructions" id="instructions" rows="3"
                              placeholder="Special instructions for the repair team…">{{ old('instructions') }}</textarea>
                    @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

    </div>

    <div class="col-md-4">
        <div class="card bg-light border-0">
            <div class="card-body">
                <h6 class="fw-semibold mb-2"><i class="bi bi-info-circle me-1 text-primary"></i>How it works</h6>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Select an approved estimate</li>
                    <li>Available repair categories are loaded from the estimate's unassigned lines</li>
                    <li>Each category creates a separate work order for a different team</li>
                    <li>Preview the exact lines included before confirming</li>
                    <li>WO starts in <strong>Pending</strong> status</li>
                </ul>
                <hr class="my-2">
                <a href="{{ route('masters.repair-category-mappings.index') }}" target="_blank" class="small">
                    <i class="bi bi-diagram-3 me-1"></i>Manage category mapping rules
                </a>
            </div>
        </div>

        <div class="d-grid gap-2 mt-3">
            <button type="submit" class="btn btn-primary d-none" id="submitBtn">
                <i class="bi bi-plus-circle me-1"></i>Create Work Order
            </button>
            <a href="{{ route('work-orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
            </a>
        </div>
    </div>
</form>

@endif

@endsection

@push('scripts')
<script>
// Runs after app.blade.php's DOMContentLoaded (Select2 init) because this
// listener is registered later in the DOM (line 1300 vs line 1195).
document.addEventListener('DOMContentLoaded', function () {
    const estimateSelect  = document.getElementById('estimate_id');
    const categoryCard    = document.getElementById('categoryCard');
    const categoryLoading = document.getElementById('categoryLoading');
    const categoryButtons = document.getElementById('categoryButtons');
    const categoryInput   = document.getElementById('repair_category_id');
    const uncatWarning    = document.getElementById('uncatWarning');
    const uncatText       = document.getElementById('uncatText');
    const previewCard     = document.getElementById('previewCard');
    const previewBody     = document.getElementById('previewBody');
    const previewCount    = document.getElementById('previewCount');
    const detailsCard     = document.getElementById('detailsCard');
    const submitBtn       = document.getElementById('submitBtn');

    if (!estimateSelect) return;

    function loadCategories() {
        const opt = estimateSelect.options[estimateSelect.selectedIndex];
        const url = opt ? opt.dataset.categoriesUrl : null;

        resetFrom('category');

        if (!url) {
            categoryCard.classList.add('d-none');
            return;
        }

        categoryCard.classList.remove('d-none');
        categoryLoading.classList.remove('d-none');
        categoryButtons.innerHTML = '';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                categoryLoading.classList.add('d-none');

                if (data.categories.length === 0) {
                    categoryButtons.innerHTML =
                        '<span class="text-muted small">No categorised unassigned lines found for this estimate.</span>';
                } else {
                    data.categories.forEach(cat => {
                        const btn = document.createElement('button');
                        btn.type      = 'button';
                        btn.className = 'btn btn-outline-secondary btn-sm category-btn';
                        btn.dataset.id         = cat.id;
                        btn.dataset.previewUrl = '{{ url("work-orders") }}/' + estimateSelect.value + '/preview-lines/' + cat.id;
                        btn.innerHTML =
                            `<span class="badge bg-${cat.color} me-1">${cat.code}</span>` +
                            `${cat.name} <span class="badge bg-light text-dark border ms-1">${cat.line_count} lines</span>`;
                        btn.addEventListener('click', () => selectCategory(btn, cat));
                        categoryButtons.appendChild(btn);
                    });
                }

                if (data.uncategorised_count > 0) {
                    uncatText.textContent =
                        data.uncategorised_count + ' line item(s) have no category and will not appear here. ';
                    uncatWarning.classList.remove('d-none');
                }
            })
            .catch(() => {
                categoryLoading.classList.add('d-none');
                categoryButtons.innerHTML = '<span class="text-danger small"><i class="bi bi-wifi-off me-1"></i>Failed to load categories.</span>';
            });
    }

    // select2:select fires when the user picks an option from the Select2 UI
    $(estimateSelect).on('select2:select', loadCategories);
    $(estimateSelect).on('select2:clear', function () {
        resetFrom('category');
        categoryCard.classList.add('d-none');
    });

    // Auto-load categories when page opens with a pre-selected estimate
    if (estimateSelect.value) {
        loadCategories();
    }

    function selectCategory(btn, cat) {
        document.querySelectorAll('.category-btn').forEach(b => {
            b.classList.remove('btn-primary', 'active');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary', 'active');

        categoryInput.value = cat.id;
        resetFrom('preview');

        previewCard.classList.remove('d-none');
        previewBody.innerHTML =
            '<tr><td colspan="6" class="text-center text-muted py-2">' +
            '<span class="spinner-border spinner-border-sm"></span> Loading…</td></tr>';

        fetch(btn.dataset.previewUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                previewCount.textContent = data.lines.length + ' lines';
                if (data.lines.length === 0) {
                    previewBody.innerHTML =
                        '<tr><td colspan="6" class="text-muted text-center py-2">No lines found.</td></tr>';
                } else {
                    previewBody.innerHTML = data.lines.map(l =>
                        `<tr class="small">
                            <td class="ps-3">${l.location}</td>
                            <td>${l.component}</td>
                            <td>${l.damage}</td>
                            <td>${l.repair}</td>
                            <td class="text-end">${parseFloat(l.qty || 0).toFixed(2)}</td>
                            <td class="text-end pe-3">${parseFloat(l.line_amount || 0).toFixed(2)}</td>
                        </tr>`
                    ).join('');
                }
                detailsCard.classList.remove('d-none');
                submitBtn.classList.remove('d-none');
            });
    }

    function resetFrom(step) {
        if (step === 'category') {
            categoryInput.value = '';
            uncatWarning.classList.add('d-none');
            uncatText.textContent = '';
        }
        previewCard.classList.add('d-none');
        previewBody.innerHTML = '';
        previewCount.textContent = '';
        detailsCard.classList.add('d-none');
        submitBtn.classList.add('d-none');
    }
});
</script>
@endpush
