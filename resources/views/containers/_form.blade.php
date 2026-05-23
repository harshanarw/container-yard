@php
    $isEdit  = !is_null($container);
    $action  = $isEdit ? route('containers.update', $container) : route('containers.store');
    $method  = $isEdit ? 'PUT' : 'POST';
@endphp

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<form method="POST" action="{{ $action }}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row g-4">

        {{-- Left column --}}
        <div class="col-lg-8">

            {{-- Identity --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-upc me-2 text-primary"></i>Container Identity
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                            <input type="text" name="container_no"
                                   class="form-control text-uppercase @error('container_no') is-invalid @enderror"
                                   placeholder="e.g. MSCU1234560"
                                   value="{{ old('container_no', $container?->container_no) }}"
                                   maxlength="12"
                                   {{ $isEdit ? 'readonly' : 'required' }}>
                            @error('container_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">ISO 6346 format: 4 letters + 7 digits</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                <option value="">— Select —</option>
                                @foreach(\App\Models\Container::CATEGORIES as $val => $label)
                                    <option value="{{ $val }}" {{ old('category', $container?->category) === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Manufacture Year</label>
                            <input type="number" name="manufacture_year"
                                   class="form-control @error('manufacture_year') is-invalid @enderror"
                                   placeholder="{{ date('Y') }}"
                                   min="1970" max="{{ date('Y') + 1 }}"
                                   value="{{ old('manufacture_year', $container?->manufacture_year) }}">
                            @error('manufacture_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Equipment Type</label>
                            <select name="equipment_type_id" id="equipmentTypeSelect"
                                    class="form-select @error('equipment_type_id') is-invalid @enderror">
                                <option value="">— Select —</option>
                                @foreach($equipmentTypes as $et)
                                    <option value="{{ $et->id }}"
                                        data-size="{{ $et->size }}"
                                        data-typecode="{{ $et->type_code }}"
                                        {{ old('equipment_type_id', $container?->equipment_type_id) == $et->id ? 'selected' : '' }}>
                                        {{ $et->name }} ({{ $et->size }}ft {{ $et->type_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('equipment_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Manufacturer</label>
                            <input type="text" name="manufacturer"
                                   class="form-control @error('manufacturer') is-invalid @enderror"
                                   placeholder="e.g. CIMC, Singamas"
                                   value="{{ old('manufacturer', $container?->manufacturer) }}"
                                   maxlength="100">
                            @error('manufacturer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ownership --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-person-badge me-2 text-primary"></i>Ownership Details
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Owner Code</label>
                            <input type="text" name="owner_code"
                                   class="form-control text-uppercase @error('owner_code') is-invalid @enderror"
                                   placeholder="e.g. MSK"
                                   value="{{ old('owner_code', $container?->owner_code) }}"
                                   maxlength="20">
                            @error('owner_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Owner Name</label>
                            <input type="text" name="owner_name"
                                   class="form-control @error('owner_name') is-invalid @enderror"
                                   placeholder="e.g. Maersk Line"
                                   value="{{ old('owner_name', $container?->owner_name) }}"
                                   maxlength="100">
                            @error('owner_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Linked Customer</label>
                            <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                                <option value="">— None —</option>
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->id }}" {{ old('customer_id', $container?->customer_id) == $cust->id ? 'selected' : '' }}>
                                        {{ $cust->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Weight specs --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-speedometer2 me-2 text-primary"></i>Weight Specifications (kg)
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gross Weight</label>
                            <input type="number" name="gross_weight_kg" step="0.01"
                                   class="form-control @error('gross_weight_kg') is-invalid @enderror"
                                   placeholder="30,480"
                                   value="{{ old('gross_weight_kg', $container?->gross_weight_kg) }}">
                            @error('gross_weight_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tare Weight</label>
                            <input type="number" name="tare_weight_kg" step="0.01"
                                   class="form-control @error('tare_weight_kg') is-invalid @enderror"
                                   placeholder="2,250"
                                   value="{{ old('tare_weight_kg', $container?->tare_weight_kg) }}">
                            @error('tare_weight_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Payload</label>
                            <input type="number" name="max_payload_kg" step="0.01"
                                   class="form-control @error('max_payload_kg') is-invalid @enderror"
                                   placeholder="28,230"
                                   value="{{ old('max_payload_kg', $container?->max_payload_kg) }}">
                            @error('max_payload_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-chat-text me-2 text-primary"></i>Notes
                </div>
                <div class="card-body">
                    <textarea name="notes" rows="3"
                              class="form-control @error('notes') is-invalid @enderror"
                              placeholder="Any additional notes about this container…">{{ old('notes', $container?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

        </div>

        {{-- Right column --}}
        <div class="col-lg-4">

            {{-- CSC Plate --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-shield-check me-2 text-primary"></i>CSC Safety Plate
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">CSC Plate Number</label>
                        <input type="text" name="csc_plate_no"
                               class="form-control @error('csc_plate_no') is-invalid @enderror"
                               placeholder="e.g. CSC-2019-001234"
                               value="{{ old('csc_plate_no', $container?->csc_plate_no) }}"
                               maxlength="50">
                        @error('csc_plate_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">CSC Expiry Date</label>
                        <input type="date" name="csc_expiry_date"
                               class="form-control @error('csc_expiry_date') is-invalid @enderror"
                               value="{{ old('csc_expiry_date', $container?->csc_expiry_date?->format('Y-m-d')) }}">
                        @error('csc_expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Quick info when editing --}}
            @if($isEdit)
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-info-circle me-2 text-primary"></i>Current Status
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-6 text-muted">Status</dt>
                        <dd class="col-6">
                            @php
                                $sc = ['in_yard'=>'success','in_repair'=>'warning','reserved'=>'info','released'=>'secondary'];
                            @endphp
                            <span class="badge bg-{{ $sc[$container->status] ?? 'secondary' }}">
                                {{ str_replace('_',' ', ucfirst($container->status ?? '')) }}
                            </span>
                        </dd>
                        <dt class="col-6 text-muted">Location</dt>
                        <dd class="col-6">
                            @if($container->location_zone)
                                {{ $container->location_zone }}-{{ $container->location_row }}{{ $container->location_bay }}-T{{ $container->location_tier }}
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-6 text-muted">Gate In</dt>
                        <dd class="col-6">{{ $container->gate_in_date?->format('d M Y') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
            @endif

        </div>

    </div>

    <div class="d-flex gap-2 mt-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i>{{ $isEdit ? 'Save Changes' : 'Create Container' }}
        </button>
        <a href="{{ $isEdit ? route('containers.show', $container) : route('containers.index') }}"
           class="btn btn-outline-secondary">Cancel</a>
    </div>

</form>

@push('scripts')
<script>
document.getElementById('equipmentTypeSelect')?.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    // No additional DOM changes needed here — sizes are derived server-side
});
</script>
@endpush
