@php
    $isEdit  = !is_null($container);
    $action  = $isEdit ? route('containers.update', $container) : route('containers.store');
    $cat     = old('category', $container?->category ?? 'consignee');
@endphp

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show small py-2" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{--
  NOTE ON SEPARATION OF CONCERNS
  ─────────────────────────────────────────────────────────────────────────────
  This form manages MASTER PROFILE fields only:
    container_no, category, equipment specs, ownership, leasing, weight,
    CSC plate, notes, and the "default customer" (owner/lessee).

  OPERATIONAL fields (condition, cargo_status, status, location, seal_no,
  gate_in_date, gate_out_date) are managed exclusively by Gate-In / Gate-Out
  operations. They are NOT editable here.

  Full per-cycle history is stored in the gate_movements table (one row per
  gate event). The containers table holds 1 master record per container plus
  a snapshot of its current state in the yard.
  ─────────────────────────────────────────────────────────────────────────────
--}}

<form method="POST" action="{{ $action }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="row g-4">

        {{-- ── LEFT COLUMN ─────────────────────────────────────────────────── --}}
        <div class="col-lg-8">

            {{-- Container Identity --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-upc me-2 text-primary"></i>Container Identity
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Container Number <span class="text-danger">*</span></label>
                            <input type="text" name="container_no"
                                   class="form-control text-uppercase font-monospace @error('container_no') is-invalid @enderror"
                                   placeholder="MSCU1234560"
                                   value="{{ old('container_no', $container?->container_no) }}"
                                   maxlength="12"
                                   {{ $isEdit ? 'readonly' : 'required' }}>
                            @error('container_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">ISO 6346 format — 4 letters + 7 digits</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="categorySelect"
                                    class="form-select @error('category') is-invalid @enderror" required>
                                @foreach(\App\Models\Container::CATEGORIES as $val => $label)
                                    <option value="{{ $val }}" {{ $cat === $val ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">
                                Consignee = customer's box · Owned = yard-owned · Leased = rented in
                            </div>
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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Equipment Type</label>
                            <select name="equipment_type_id" id="equipmentTypeSelect"
                                    class="form-select @error('equipment_type_id') is-invalid @enderror">
                                <option value="">— Select —</option>
                                @foreach($equipmentTypes as $et)
                                    <option value="{{ $et->id }}"
                                        {{ old('equipment_type_id', $container?->equipment_type_id) == $et->id ? 'selected' : '' }}>
                                        {{ $et->name }} ({{ $et->size }}ft {{ $et->type_code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('equipment_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Manufacturer</label>
                            <input type="text" name="manufacturer"
                                   class="form-control @error('manufacturer') is-invalid @enderror"
                                   placeholder="e.g. CIMC, Singamas, Triton"
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
                    <i class="bi bi-person-badge me-2 text-primary"></i>Ownership
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Owner / BIC Code</label>
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
                            <label class="form-label fw-semibold">
                                Linked Customer
                                <span class="text-muted fw-normal" style="font-size:.75rem;">(default)</span>
                            </label>
                            <select name="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
                                <option value="">— None —</option>
                                @foreach($customers as $cust)
                                    <option value="{{ $cust->id }}"
                                        {{ old('customer_id', $container?->customer_id) == $cust->id ? 'selected' : '' }}>
                                        {{ $cust->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">For consignee boxes this is overridden at each Gate-In.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Leasing Details — shown only when category = leased --}}
            <div class="card content-card mb-4 border-warning" id="leasingSection"
                 style="{{ $cat === 'leased' ? '' : 'display:none;' }}">
                <div class="card-header py-2 bg-warning-subtle">
                    <i class="bi bi-file-earmark-text me-2 text-warning"></i>Leasing Details
                    <span class="badge bg-warning text-dark ms-2 small">Required for Leased containers</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Lessor Code</label>
                            <input type="text" name="lessor_code"
                                   class="form-control text-uppercase @error('lessor_code') is-invalid @enderror"
                                   placeholder="e.g. TRITON"
                                   value="{{ old('lessor_code', $container?->lessor_code) }}"
                                   maxlength="30">
                            @error('lessor_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Lessor Name <span class="text-danger">*</span></label>
                            <input type="text" name="lessor_name"
                                   class="form-control @error('lessor_name') is-invalid @enderror"
                                   placeholder="e.g. Triton International Ltd"
                                   value="{{ old('lessor_name', $container?->lessor_name) }}"
                                   maxlength="150">
                            @error('lessor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">The company you are leasing this container from.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lease / Contract Ref.</label>
                            <input type="text" name="lease_reference"
                                   class="form-control @error('lease_reference') is-invalid @enderror"
                                   placeholder="e.g. LCA-2024-00123"
                                   value="{{ old('lease_reference', $container?->lease_reference) }}"
                                   maxlength="100">
                            @error('lease_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lease Start Date</label>
                            <input type="date" name="lease_start_date"
                                   class="form-control @error('lease_start_date') is-invalid @enderror"
                                   value="{{ old('lease_start_date', $container?->lease_start_date?->format('Y-m-d')) }}">
                            @error('lease_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Lease End Date</label>
                            <input type="date" name="lease_end_date"
                                   class="form-control @error('lease_end_date') is-invalid @enderror"
                                   value="{{ old('lease_end_date', $container?->lease_end_date?->format('Y-m-d')) }}">
                            @error('lease_end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            @php
                                $leaseEndDate = old('lease_end_date', $container?->lease_end_date?->format('Y-m-d'));
                                $daysToExpiry = $leaseEndDate ? \Carbon\Carbon::parse($leaseEndDate)->diffInDays(now(), false) : null;
                            @endphp
                            @if($leaseEndDate)
                                <div class="d-flex align-items-end h-100 pb-1">
                                    @if($daysToExpiry > 0)
                                        <div class="alert alert-danger py-1 px-2 small mb-0 w-100">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            Lease expired {{ $daysToExpiry }} day(s) ago
                                        </div>
                                    @elseif($daysToExpiry >= -90)
                                        <div class="alert alert-warning py-1 px-2 small mb-0 w-100">
                                            <i class="bi bi-clock me-1"></i>
                                            Expires in {{ abs($daysToExpiry) }} day(s)
                                        </div>
                                    @else
                                        <div class="text-muted small">
                                            <i class="bi bi-check-circle text-success me-1"></i>
                                            Lease active
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Weight Specifications --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-speedometer2 me-2 text-primary"></i>Weight Specifications (kg)
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gross Weight</label>
                            <div class="input-group">
                                <input type="number" name="gross_weight_kg" step="0.01"
                                       class="form-control @error('gross_weight_kg') is-invalid @enderror"
                                       placeholder="30480"
                                       value="{{ old('gross_weight_kg', $container?->gross_weight_kg) }}">
                                <span class="input-group-text">kg</span>
                            </div>
                            @error('gross_weight_kg')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tare Weight</label>
                            <div class="input-group">
                                <input type="number" name="tare_weight_kg" step="0.01"
                                       class="form-control @error('tare_weight_kg') is-invalid @enderror"
                                       placeholder="2250"
                                       value="{{ old('tare_weight_kg', $container?->tare_weight_kg) }}">
                                <span class="input-group-text">kg</span>
                            </div>
                            @error('tare_weight_kg')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Payload</label>
                            <div class="input-group">
                                <input type="number" name="max_payload_kg" step="0.01"
                                       class="form-control @error('max_payload_kg') is-invalid @enderror"
                                       placeholder="28230"
                                       value="{{ old('max_payload_kg', $container?->max_payload_kg) }}">
                                <span class="input-group-text">kg</span>
                            </div>
                            @error('max_payload_kg')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
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
                              placeholder="Any additional notes…">{{ old('notes', $container?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

        </div>

        {{-- ── RIGHT COLUMN ────────────────────────────────────────────────── --}}
        <div class="col-lg-4">

            {{-- CSC Safety Plate --}}
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-shield-check me-2 text-primary"></i>CSC Safety Plate
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Plate Number</label>
                        <input type="text" name="csc_plate_no"
                               class="form-control @error('csc_plate_no') is-invalid @enderror"
                               placeholder="e.g. CSC-2019-001234"
                               value="{{ old('csc_plate_no', $container?->csc_plate_no) }}"
                               maxlength="50">
                        @error('csc_plate_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="form-label fw-semibold">Expiry Date</label>
                        <input type="date" name="csc_expiry_date"
                               class="form-control @error('csc_expiry_date') is-invalid @enderror"
                               value="{{ old('csc_expiry_date', $container?->csc_expiry_date?->format('Y-m-d')) }}">
                        @error('csc_expiry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            {{-- Operational snapshot (read-only, shown on edit only) --}}
            @if($isEdit)
            <div class="card content-card mb-4">
                <div class="card-header py-2">
                    <i class="bi bi-geo-alt me-2 text-primary"></i>Current Yard Status
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem; font-weight:400;">Read-only</span>
                </div>
                <div class="card-body small">
                    <p class="text-muted" style="font-size:.75rem; margin-bottom:.75rem;">
                        These fields are updated automatically by Gate-In / Gate-Out operations.
                        To view the full cycle history, see the
                        <a href="{{ route('containers.show', $container) }}">Container Profile</a>.
                    </p>
                    @php
                        $sc = ['in_yard'=>'success','in_repair'=>'warning text-dark','reserved'=>'info','released'=>'secondary'];
                    @endphp
                    <dl class="row mb-0" style="--bs-gutter-x:0;">
                        <dt class="col-6 text-muted fw-normal">Status</dt>
                        <dd class="col-6">
                            <span class="badge bg-{{ $sc[$container->status] ?? 'secondary' }}">
                                {{ str_replace('_',' ',ucfirst($container->status ?? '')) }}
                            </span>
                        </dd>
                        <dt class="col-6 text-muted fw-normal">Location</dt>
                        <dd class="col-6 fw-semibold">
                            @if($container->location_zone)
                                {{ $container->location_zone }}-{{ $container->location_row }}{{ $container->location_bay }}-T{{ $container->location_tier }}
                            @else —
                            @endif
                        </dd>
                        <dt class="col-6 text-muted fw-normal">Condition</dt>
                        <dd class="col-6">{{ ucfirst(str_replace('_',' ',$container->condition ?? '')) ?: '—' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Cargo</dt>
                        <dd class="col-6">{{ ucfirst($container->cargo_status ?? '') ?: '—' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Gate In</dt>
                        <dd class="col-6">{{ $container->gate_in_date?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Gate Out</dt>
                        <dd class="col-6">{{ $container->gate_out_date?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-6 text-muted fw-normal">Gate cycles</dt>
                        <dd class="col-6">{{ $container->gateMovements()->count() }}</dd>
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
(function () {
    const catSel     = document.getElementById('categorySelect');
    const leaseBlock = document.getElementById('leasingSection');

    function toggleLease() {
        if (catSel.value === 'leased') {
            leaseBlock.style.display = '';
        } else {
            leaseBlock.style.display = 'none';
        }
    }

    catSel.addEventListener('change', toggleLease);
    toggleLease(); // run on load in case of old() repopulation
})();
</script>
@endpush
